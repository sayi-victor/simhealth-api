<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\ClinicalOrder;
use App\Models\Document;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Storage::fake((string) config('documents.disk'));
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    private function pdf(string $name = 'labs.pdf', int $kilobytes = 64): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_unauthenticated_document_requests_return_401(): void
    {
        $patient = Patient::factory()->create();

        $this->getJson("/api/patients/{$patient->id}/documents")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson("/api/patients/{$patient->id}/documents", ['file' => $this->pdf()])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_upload_list_search_download_and_delete_patient_document(): void
    {
        $clinician = $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        $created = $this->postJson("/api/patients/{$patient->id}/documents", [
            'file' => $this->pdf('labs.pdf'),
            'category' => 'lab_report',
        ])->assertCreated()
            ->assertJsonPath('data.original_name', 'labs.pdf')
            ->assertJsonPath('data.category', 'lab_report')
            ->assertJsonPath('data.uploaded_by.id', $clinician->id);

        $documentId = $created->json('data.id');
        $this->assertGreaterThan(0, $created->json('data.size_bytes'));
        $this->assertSame(64, strlen($created->json('data.sha256')));
        $this->assertArrayNotHasKey('path', $created->json('data'));
        $this->assertArrayNotHasKey('disk', $created->json('data'));

        // The file really landed on the private disk, under a dated folder.
        $document = Document::findOrFail($documentId);
        Storage::disk($document->disk)->assertExists($document->path);
        $this->assertStringStartsWith('documents/'.now()->format('Y/m').'/', $document->path);

        // Listing, category filter and search are scoped to the parent.
        $this->getJson("/api/patients/{$patient->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/patients/{$patient->id}/documents?category=consent")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/patients/{$patient->id}/documents?q=labs")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/documents/{$documentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $documentId);

        $download = $this->get("/api/documents/{$documentId}/download");
        $download->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-type', 'application/octet-stream');

        $disposition = (string) $download->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('labs.pdf', $disposition);

        $this->deleteJson("/api/documents/{$documentId}")->assertNoContent();
        $this->assertSoftDeleted('documents', ['id' => $documentId]);

        // Soft deleted records are gone from the API surface, but the file stays
        // on disk for the clinical retention period.
        $this->getJson("/api/documents/{$documentId}")->assertNotFound();
        Storage::disk($document->disk)->assertExists($document->path);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/documents", [
            'file' => UploadedFile::fake()->create('payload.exe', 8, 'application/x-msdownload'),
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('file', $response->json('error.details'));
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_rejects_files_over_the_configured_size_limit(): void
    {
        config(['documents.max_kb' => 1]);

        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/documents", [
            'file' => $this->pdf('too-big.pdf', 2),
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('file', $response->json('error.details'));
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_rbac_limits_document_access_by_role(): void
    {
        $patient = Patient::factory()->create();

        // Clinician uploads and later deletes.
        $this->actingAsRole('clinician');
        $documentId = $this->postJson("/api/patients/{$patient->id}/documents", [
            'file' => $this->pdf(),
        ])->assertCreated()->json('data.id');

        // Inventory staff have no document permissions at all.
        $this->actingAsRole('inventory');
        $this->getJson("/api/patients/{$patient->id}/documents")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->postJson("/api/patients/{$patient->id}/documents", ['file' => $this->pdf()])
            ->assertStatus(403);
        $this->deleteJson("/api/documents/{$documentId}")->assertStatus(403);

        // Pharmacy may read but not upload or delete.
        $this->actingAsRole('pharmacy');
        $this->getJson("/api/patients/{$patient->id}/documents")->assertOk();
        $this->postJson("/api/patients/{$patient->id}/documents", ['file' => $this->pdf()])
            ->assertStatus(403);
        $this->deleteJson("/api/documents/{$documentId}")->assertStatus(403);

        // The document survived every rejected attempt.
        $this->assertDatabaseCount('documents', 1);
        $this->assertNotSoftDeleted('documents', ['id' => $documentId]);
    }

    public function test_encounter_and_order_documents_are_listed_per_parent(): void
    {
        $this->actingAsRole('clinician');

        $patient = Patient::factory()->create();
        $encounter = Encounter::factory()->create(['patient_id' => $patient->id]);
        $order = ClinicalOrder::create([
            'order_number' => 'ORD-DOC-0001',
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'order_type' => 'laboratory',
            'priority' => 'routine',
            'status' => 'ordered',
            'ordered_at' => now(),
        ]);

        $this->postJson("/api/encounters/{$encounter->id}/documents", [
            'file' => UploadedFile::fake()->create('consent.png', 32, 'image/png'),
            'category' => 'consent',
        ])->assertCreated()->assertJsonPath('data.category', 'consent');

        $this->postJson("/api/orders/{$order->id}/documents", [
            'file' => $this->pdf('result.pdf'),
            'category' => 'lab_report',
        ])->assertCreated()->assertJsonPath('data.category', 'lab_report');

        $this->getJson("/api/encounters/{$encounter->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'consent.png');

        $this->getJson("/api/orders/{$order->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'result.pdf');

        // Attachments belong to their parent only, never to the patient directly.
        $this->getJson("/api/patients/{$patient->id}/documents")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Encounter::class,
            'documentable_id' => $encounter->id,
        ]);
        $this->assertDatabaseHas('documents', [
            'documentable_type' => ClinicalOrder::class,
            'documentable_id' => $order->id,
        ]);
    }

    public function test_document_categories_come_from_config(): void
    {
        $this->actingAsRole('clinician');

        $response = $this->getJson('/api/document-categories')
            ->assertOk()
            ->assertJsonPath('data.0.value', 'general')
            ->assertJsonPath('data.0.label', 'General');

        $this->assertCount(count((array) config('documents.categories')), $response->json('data'));
    }

    public function test_upload_is_audited_without_phi(): void
    {
        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        $this->postJson("/api/patients/{$patient->id}/documents", [
            'file' => $this->pdf(),
        ])->assertCreated();

        $log = AuditLog::query()->where('event', 'documents.uploaded')->firstOrFail();

        $this->assertSame('documents', $log->category);
        $this->assertTrue($log->success);
        $this->assertSame(['document_id', 'documentable_type', 'documentable_id', 'category', 'size_bytes'], array_keys($log->metadata));
        $this->assertStringNotContainsString($patient->mrn, json_encode($log->metadata));
        $this->assertStringNotContainsString($patient->last_name, json_encode($log->metadata));
    }
}

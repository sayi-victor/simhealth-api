<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Document>
     */
    public function listFor(Model $documentable, array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

        $query = $documentable->documents()
            ->with('uploader:id,first_name,middle_name,last_name');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('original_name', 'like', $like)
                    ->orWhere('mime_type', 'like', $like);
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(
        Model $documentable,
        UploadedFile $file,
        array $data,
        User $actor,
        Request $request,
    ): Document {
        $disk = (string) config('documents.disk', 'local');
        $path = $file->store('documents/'.now()->format('Y/m'), $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the uploaded document.');
        }

        try {
            $document = DB::transaction(function () use ($documentable, $file, $data, $actor, $request, $disk, $path): Document {
                $document = $documentable->documents()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => mb_substr($this->resolveMimeType($file), 0, 120),
                    'size_bytes' => (int) $file->getSize(),
                    'sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
                    'category' => $data['category'] ?? 'general',
                    'uploaded_by' => $actor->id,
                ]);

                $this->audit->log(
                    event: 'documents.uploaded',
                    category: 'documents',
                    user: $actor,
                    auditable: $document,
                    success: true,
                    metadata: [
                        'document_id' => $document->id,
                        'documentable_type' => $documentable->getMorphClass(),
                        'documentable_id' => $documentable->getKey(),
                        'category' => $document->category,
                        'size_bytes' => $document->size_bytes,
                    ],
                    request: $request,
                );

                return $document;
            });
        } catch (Throwable $e) {
            // The record failed to persist, so the stored file is orphaned.
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        return $document->load('uploader:id,first_name,middle_name,last_name');
    }

    public function download(Document $document, User $actor, Request $request): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->path)) {
            abort(404);
        }

        $this->audit->log(
            event: 'documents.downloaded',
            category: 'documents',
            user: $actor,
            auditable: $document,
            success: true,
            metadata: ['document_id' => $document->id],
            request: $request,
        );

        return $disk->download($document->path, $document->original_name, [
            // Always an attachment: never let a stored HTML/SVG file render.
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Soft delete the record but keep the stored file: clinical records have a
     * retention requirement, so nothing is destroyed from disk here.
     */
    public function delete(Document $document, User $actor, Request $request): void
    {
        DB::transaction(function () use ($document, $actor, $request): void {
            $document->delete();

            $this->audit->log(
                event: 'documents.deleted',
                category: 'documents',
                user: $actor,
                auditable: $document,
                success: true,
                metadata: ['document_id' => $document->id],
                request: $request,
            );
        });
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        return $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
    }
}

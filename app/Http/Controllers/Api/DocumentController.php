<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentIndexRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\ClinicalOrder;
use App\Models\Document;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    public function indexForPatient(DocumentIndexRequest $request, Patient $patient): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            $this->documents->listFor($patient, $request->validated()),
        );
    }

    public function storeForPatient(StoreDocumentRequest $request, Patient $patient): JsonResponse
    {
        return $this->upload($request, $patient);
    }

    public function indexForEncounter(DocumentIndexRequest $request, Encounter $encounter): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            $this->documents->listFor($encounter, $request->validated()),
        );
    }

    public function storeForEncounter(StoreDocumentRequest $request, Encounter $encounter): JsonResponse
    {
        return $this->upload($request, $encounter);
    }

    public function indexForOrder(DocumentIndexRequest $request, ClinicalOrder $order): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            $this->documents->listFor($order, $request->validated()),
        );
    }

    public function storeForOrder(StoreDocumentRequest $request, ClinicalOrder $order): JsonResponse
    {
        return $this->upload($request, $order);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('uploader:id,first_name,middle_name,last_name'));
    }

    public function categories(): JsonResponse
    {
        $categories = array_map(
            fn (string $slug): array => [
                'value' => $slug,
                'label' => Str::headline($slug),
            ],
            array_values((array) config('documents.categories', ['general'])),
        );

        return response()->json(['data' => $categories]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->documents->download($document, $actor, $request);
    }

    public function destroy(Request $request, Document $document): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->documents->delete($document, $actor, $request);

        return response()->noContent();
    }

    private function upload(StoreDocumentRequest $request, Model $documentable): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $document = $this->documents->upload($documentable, $file, $request->validated(), $actor, $request);

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }
}

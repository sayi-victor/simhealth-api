<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            // Absolute URL. The SPA uses /documents/{id}/download with its bearer token.
            'download_url' => url("/api/documents/{$this->id}/download"),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->id,
                'full_name' => $this->uploader->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

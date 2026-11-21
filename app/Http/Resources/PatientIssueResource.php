<?php

namespace App\Http\Resources;

use App\Models\PatientIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PatientIssue */
class PatientIssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => $this->type,
            'title' => $this->title,
            'code' => $this->code,
            'severity' => $this->severity,
            'reaction' => $this->reaction,
            'onset_date' => $this->onset_date?->toDateString(),
            'resolved_date' => $this->resolved_date?->toDateString(),
            'is_active' => $this->is_active,
            'comments' => $this->comments,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Immunization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Immunization */
class ImmunizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'vaccine_name' => $this->vaccine_name,
            'cvx_code' => $this->cvx_code,
            'dose_number' => $this->dose_number,
            'dose_amount' => $this->dose_amount,
            'dose_unit' => $this->dose_unit,
            'route' => $this->route,
            'site' => $this->site,
            'manufacturer' => $this->manufacturer,
            'lot_number' => $this->lot_number,
            'expires_at' => $this->expires_at?->toDateString(),
            'administered_at' => $this->administered_at?->toIso8601String(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'administered_by' => $this->whenLoaded('administrator', fn () => $this->administrator?->full_name),
        ];
    }
}

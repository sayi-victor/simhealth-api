<?php

namespace App\Http\Resources;

use App\Models\Admission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Admission */
class AdmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_number' => $this->admission_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'ward_id' => $this->ward_id,
            'bed_id' => $this->bed_id,
            'admission_type' => $this->admission_type,
            'status' => $this->status,
            'admitted_at' => $this->admitted_at?->toIso8601String(),
            'expected_discharge_date' => $this->expected_discharge_date?->toDateString(),
            'discharged_at' => $this->discharged_at?->toIso8601String(),
            'admission_diagnosis' => $this->admission_diagnosis,
            'admission_notes' => $this->admission_notes,
            'discharge_diagnosis' => $this->discharge_diagnosis,
            'discharge_summary' => $this->discharge_summary,
            'discharge_disposition' => $this->discharge_disposition,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'ward' => $this->whenLoaded('ward', fn () => $this->ward ? [
                'id' => $this->ward->id,
                'code' => $this->ward->code,
                'name' => $this->ward->name,
                'category' => $this->ward->category,
                'daily_rate' => $this->ward->daily_rate,
            ] : null),
            'bed' => $this->whenLoaded('bed', fn () => $this->bed ? [
                'id' => $this->bed->id,
                'bed_number' => $this->bed->bed_number,
                'status' => $this->bed->status,
            ] : null),
            'admitting_provider' => $this->whenLoaded('admittingProvider', fn () => $this->admittingProvider ? [
                'id' => $this->admittingProvider->id,
                'full_name' => $this->admittingProvider->full_name,
            ] : null),
        ];
    }
}

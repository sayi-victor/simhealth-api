<?php

namespace App\Http\Resources;

use App\Models\Surgery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Surgery */
class SurgeryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'surgery_number' => $this->surgery_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'admission_id' => $this->admission_id,
            'procedure_name' => $this->procedure_name,
            'procedure_code' => $this->procedure_code,
            'theater_room' => $this->theater_room,
            'priority' => $this->priority,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'anesthesia_type' => $this->anesthesia_type,
            'consent_signed' => $this->consent_signed,
            'pre_op_diagnosis' => $this->pre_op_diagnosis,
            'post_op_diagnosis' => $this->post_op_diagnosis,
            'operative_notes' => $this->operative_notes,
            'complications' => $this->complications,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'surgeon' => $this->whenLoaded('surgeon', fn () => $this->surgeon ? [
                'id' => $this->surgeon->id,
                'full_name' => $this->surgeon->full_name,
            ] : null),
        ];
    }
}

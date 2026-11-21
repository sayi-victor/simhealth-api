<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prescription */
class PrescriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_number' => $this->prescription_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'provider_id' => $this->provider_id,
            'drug_id' => $this->drug_id,
            'drug_name' => $this->drug_name,
            'dosage' => $this->dosage,
            'route' => $this->route,
            'frequency' => $this->frequency,
            'duration_days' => $this->duration_days,
            'quantity_prescribed' => $this->quantity_prescribed,
            'quantity_dispensed' => $this->quantity_dispensed,
            'quantity_remaining' => max(0, $this->quantity_prescribed - $this->quantity_dispensed),
            'refills_allowed' => $this->refills_allowed,
            'is_prn' => $this->is_prn,
            'allow_substitution' => $this->allow_substitution,
            'inhouse' => $this->inhouse,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'indication' => $this->indication,
            'instructions' => $this->instructions,
            'status' => $this->status,
            'prescribed_at' => $this->prescribed_at?->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
            ] : null),
            'drug' => $this->whenLoaded('drug', fn () => $this->drug ? [
                'id' => $this->drug->id,
                'code' => $this->drug->code,
                'name' => $this->drug->name,
                'strength' => $this->drug->strength,
                'form' => $this->drug->form,
                'unit_price' => $this->drug->unit_price,
            ] : null),
            'dispensations' => $this->whenLoaded('dispensations', fn () => $this->dispensations->map(fn ($d) => [
                'id' => $d->id,
                'quantity' => $d->quantity,
                'unit_price' => $d->unit_price,
                'total_price' => $d->total_price,
                'lot_number' => $d->lot?->lot_number,
                'dispensed_by' => $d->dispenser?->full_name,
                'dispensed_at' => $d->dispensed_at?->toIso8601String(),
                'notes' => $d->notes,
            ])->values()->all()),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\ClinicalOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicalOrder */
class ClinicalOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'provider_id' => $this->provider_id,
            'order_type' => $this->order_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'specimen_type' => $this->specimen_type,
            'specimen_number' => $this->specimen_number,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'diagnosis' => $this->diagnosis,
            'clinical_history' => $this->clinical_history,
            'patient_instructions' => $this->patient_instructions,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'catalog_item_id' => $item->catalog_item_id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'notes' => $item->notes,
            ])->values()->all()),
            'results' => $this->whenLoaded('results', fn () => $this->results->map(fn ($res) => [
                'id' => $res->id,
                'clinical_order_item_id' => $res->clinical_order_item_id,
                'result_code' => $res->result_code,
                'result_label' => $res->result_label,
                'value' => $res->value,
                'units' => $res->units,
                'reference_range' => $res->reference_range,
                'abnormal_flag' => $res->abnormal_flag,
                'findings' => $res->findings,
                'impression' => $res->impression,
                'comments' => $res->comments,
                'result_status' => $res->result_status,
                'resulted_by' => $res->resulter?->full_name,
                'resulted_at' => $res->resulted_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Patient */
class PatientSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrn' => $this->mrn,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'sex' => $this->sex,
            'phone_cell' => $this->phone_cell,
            'email' => $this->email,
            'city' => $this->city,
            'registration_date' => $this->registration_date?->toDateString(),
            'clinical_alert' => $this->clinical_alert,
            'facility' => $this->whenLoaded('facility', fn () => $this->facility ? [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

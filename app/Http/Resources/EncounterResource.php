<?php

namespace App\Http\Resources;

use App\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Encounter */
class EncounterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encounter_number' => $this->encounter_number,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'provider_id' => $this->provider_id,
            'encounter_date' => $this->encounter_date?->toIso8601String(),
            'onset_date' => $this->onset_date?->toDateString(),
            'visit_category' => $this->visit_category,
            'sensitivity' => $this->sensitivity,
            'status' => $this->status,
            'shift' => $this->shift,
            'room_number' => $this->room_number,
            'reason' => $this->reason,
            'referral_source' => $this->referral_source,
            'nhis_vcode' => $this->nhis_vcode,
            'price_level' => $this->price_level,
            'billing_note' => $this->billing_note,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
                'sex' => $this->patient->sex,
                'clinical_alert' => $this->patient->clinical_alert ?? null,
            ] : null),
            'facility' => $this->whenLoaded('facility', fn () => $this->facility ? [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ] : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
            ] : null),
            'signer' => $this->whenLoaded('signer', fn () => $this->signer ? [
                'id' => $this->signer->id,
                'full_name' => $this->signer->full_name,
            ] : null),
            'vitals' => $this->whenLoaded('vitals', fn () => $this->vitals->map(fn ($v) => [
                'id' => $v->id,
                'recorded_at' => $v->recorded_at?->toIso8601String(),
                'bp_systolic' => $v->bp_systolic,
                'bp_diastolic' => $v->bp_diastolic,
                'pulse' => $v->pulse,
                'respiration' => $v->respiration,
                'temperature' => $v->temperature,
                'temp_method' => $v->temp_method,
                'weight_kg' => $v->weight_kg,
                'height_cm' => $v->height_cm,
                'bmi' => $v->bmi,
                'bmi_status' => $v->bmi_status,
                'oxygen_saturation' => $v->oxygen_saturation,
                'oxygen_sat_condition' => $v->oxygen_sat_condition,
                'pain_score' => $v->pain_score,
                'triage_level' => $v->triage_level,
                'notes' => $v->notes,
                'recorded_by' => $v->recorder?->full_name,
            ])->values()->all()),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($n) => [
                'id' => $n->id,
                'note_type' => $n->note_type,
                'title' => $n->title,
                'subjective' => $n->subjective,
                'objective' => $n->objective,
                'assessment' => $n->assessment,
                'plan' => $n->plan,
                'body' => $n->body,
                'is_signed' => $n->is_signed,
                'author' => $n->author?->full_name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all()),
            'forms' => $this->whenLoaded('forms', fn () => $this->forms->map(fn ($f) => [
                'id' => $f->id,
                'form_definition_id' => $f->form_definition_id,
                'form_slug' => $f->definition?->slug,
                'form_name' => $f->definition?->name,
                'status' => $f->status,
                'data' => $f->data,
                'filled_by' => $f->filler?->full_name,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'provider_id' => $this->provider_id,
            'facility_id' => $this->facility_id,
            'category_id' => $this->category_id,
            'encounter_id' => $this->encounter_id,
            'title' => $this->title,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'all_day' => $this->all_day,
            'status' => $this->status,
            'room' => $this->room,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'send_sms_reminder' => $this->send_sms_reminder,
            'send_email_reminder' => $this->send_email_reminder,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
                'phone_cell' => $this->patient->phone_cell,
            ] : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'full_name' => $this->provider->full_name,
            ] : null),
            'facility' => $this->whenLoaded('facility', fn () => $this->facility ? [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'color' => $this->category->color,
                'default_duration_minutes' => $this->category->default_duration_minutes,
            ] : null),
            'encounter' => $this->whenLoaded('encounter', fn () => $this->encounter ? [
                'id' => $this->encounter->id,
                'encounter_number' => $this->encounter->encounter_number,
                'status' => $this->encounter->status,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

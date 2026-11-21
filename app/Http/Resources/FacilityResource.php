<?php

namespace App\Http\Resources;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facility */
class FacilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'website' => $this->website,
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'facility_npi' => $this->facility_npi,
            'pos_code' => $this->pos_code,
            'color' => $this->color,
            'tagline' => $this->tagline,
            'is_service_location' => $this->is_service_location,
            'is_billing_location' => $this->is_billing_location,
            'accepts_assignment' => $this->accepts_assignment,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

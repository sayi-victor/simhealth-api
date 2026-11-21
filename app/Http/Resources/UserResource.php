<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'suffix' => $this->suffix,
            'department' => $this->department,
            'phone' => $this->phone,
            'is_provider' => $this->is_provider,
            'show_in_calendar' => $this->show_in_calendar,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'facility' => $this->whenLoaded('facility', fn () => $this->facility ? [
                'id' => $this->facility->id,
                'name' => $this->facility->name,
                'code' => $this->facility->code,
            ] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')->values()->all()),
            'permissions' => $this->whenLoaded('roles', fn () => $this->permissionSlugs()),
        ];
    }
}

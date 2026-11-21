<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'color',
        'default_duration_minutes',
        'daily_limit',
        'provider_mandatory',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_duration_minutes' => 'integer',
            'daily_limit' => 'integer',
            'provider_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'fax',
        'email',
        'website',
        'street',
        'city',
        'state',
        'postal_code',
        'country_code',
        'federal_ein',
        'facility_npi',
        'tax_id_type',
        'pos_code',
        'color',
        'tagline',
        'is_service_location',
        'is_billing_location',
        'accepts_assignment',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pos_code' => 'integer',
            'is_service_location' => 'boolean',
            'is_billing_location' => 'boolean',
            'accepts_assignment' => 'boolean',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

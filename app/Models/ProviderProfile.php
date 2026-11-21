<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'specialty',
        'physician_type',
        'npi',
        'taxonomy',
        'state_license_number',
        'federal_drug_id',
        'federal_tax_id',
        'upin',
        'billing_name',
        'cpoe_enabled',
        'default_warehouse',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cpoe_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

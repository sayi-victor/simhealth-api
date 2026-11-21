<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immunization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'facility_id',
        'administered_by',
        'vaccine_name',
        'cvx_code',
        'dose_number',
        'dose_amount',
        'dose_unit',
        'route',
        'site',
        'manufacturer',
        'lot_number',
        'expires_at',
        'administered_at',
        'next_due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dose_number' => 'integer',
            'dose_amount' => 'decimal:2',
            'expires_at' => 'date',
            'administered_at' => 'datetime',
            'next_due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}

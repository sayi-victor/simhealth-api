<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientEmployer extends Model
{
    protected $fillable = [
        'patient_id',
        'employer_name',
        'occupation',
        'department',
        'employment_status',
        'phone',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

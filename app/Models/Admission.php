<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'admission_number',
        'patient_id',
        'encounter_id',
        'facility_id',
        'ward_id',
        'bed_id',
        'admitting_provider_id',
        'discharging_provider_id',
        'admission_type',
        'status',
        'admitted_at',
        'expected_discharge_date',
        'discharged_at',
        'admission_diagnosis',
        'admission_notes',
        'discharge_diagnosis',
        'discharge_summary',
        'discharge_disposition',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'expected_discharge_date' => 'date',
            'discharged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<Bed, $this> */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admittingProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitting_provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dischargingProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharging_provider_id');
    }
}

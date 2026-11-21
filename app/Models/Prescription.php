<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'prescription_number',
        'patient_id',
        'encounter_id',
        'provider_id',
        'facility_id',
        'drug_id',
        'drug_name',
        'dosage',
        'route',
        'frequency',
        'duration_days',
        'quantity_prescribed',
        'quantity_dispensed',
        'refills_allowed',
        'is_prn',
        'allow_substitution',
        'inhouse',
        'start_date',
        'end_date',
        'indication',
        'instructions',
        'status',
        'prescribed_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'quantity_prescribed' => 'integer',
            'quantity_dispensed' => 'integer',
            'refills_allowed' => 'integer',
            'is_prn' => 'boolean',
            'allow_substitution' => 'boolean',
            'inhouse' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'prescribed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** @return BelongsTo<Drug, $this> */
    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    /** @return HasMany<PrescriptionDispensation, $this> */
    public function dispensations(): HasMany
    {
        return $this->hasMany(PrescriptionDispensation::class)->orderByDesc('dispensed_at');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterVital extends Model
{
    protected $fillable = [
        'encounter_id',
        'patient_id',
        'recorded_at',
        'recorded_by',
        'bp_systolic',
        'bp_diastolic',
        'pulse',
        'respiration',
        'temperature',
        'temp_method',
        'weight_kg',
        'height_cm',
        'bmi',
        'bmi_status',
        'oxygen_saturation',
        'oxygen_sat_condition',
        'waist_cm',
        'head_cm',
        'pain_score',
        'triage_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'bp_systolic' => 'integer',
            'bp_diastolic' => 'integer',
            'pulse' => 'integer',
            'respiration' => 'integer',
            'temperature' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:1',
            'oxygen_saturation' => 'decimal:2',
            'waist_cm' => 'decimal:2',
            'head_cm' => 'decimal:2',
            'pain_score' => 'integer',
        ];
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientHistory extends Model
{
    protected $fillable = [
        'patient_id',
        'lifestyle',
        'family_history',
        'surgical_history',
        'preventive_exams',
        'mental_health',
        'additional_notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'lifestyle' => 'array',
            'family_history' => 'array',
            'surgical_history' => 'array',
            'preventive_exams' => 'array',
            'mental_health' => 'array',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

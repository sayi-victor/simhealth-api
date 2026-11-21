<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientIssue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'type',
        'title',
        'code',
        'severity',
        'reaction',
        'onset_date',
        'resolved_date',
        'is_active',
        'comments',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'onset_date' => 'date',
            'resolved_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

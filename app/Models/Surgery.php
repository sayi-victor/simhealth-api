<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Surgery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'surgery_number',
        'patient_id',
        'encounter_id',
        'admission_id',
        'facility_id',
        'surgeon_id',
        'anesthetist_id',
        'procedure_name',
        'procedure_code',
        'theater_room',
        'priority',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'anesthesia_type',
        'consent_signed',
        'pre_op_diagnosis',
        'post_op_diagnosis',
        'operative_notes',
        'complications',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'consent_signed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    /** @return BelongsTo<User, $this> */
    public function anesthetist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anesthetist_id');
    }
}

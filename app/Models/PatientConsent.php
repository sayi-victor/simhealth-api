<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientConsent extends Model
{
    protected $fillable = [
        'patient_id',
        'allow_sms',
        'allow_email',
        'allow_voice',
        'allow_mail',
        'hipaa_notice_received',
        'hipaa_message',
        'allow_immunization_registry',
        'allow_health_info_exchange',
        'advance_directive_completed',
        'advance_directive_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_sms' => 'boolean',
            'allow_email' => 'boolean',
            'allow_voice' => 'boolean',
            'allow_mail' => 'boolean',
            'hipaa_notice_received' => 'boolean',
            'allow_immunization_registry' => 'boolean',
            'allow_health_info_exchange' => 'boolean',
            'advance_directive_completed' => 'boolean',
            'advance_directive_reviewed_at' => 'date',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

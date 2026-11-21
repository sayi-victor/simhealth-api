<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientInsurance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'tier',
        'provider_name',
        'plan_name',
        'policy_number',
        'group_number',
        'policy_type',
        'subscriber_first_name',
        'subscriber_middle_name',
        'subscriber_last_name',
        'subscriber_relationship',
        'subscriber_dob',
        'subscriber_sex',
        'subscriber_phone',
        'subscriber_national_id',
        'copay',
        'maximum_bill',
        'accept_assignment',
        'effective_date',
        'expiration_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subscriber_dob' => 'date',
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'copay' => 'decimal:2',
            'maximum_bill' => 'decimal:2',
            'accept_assignment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

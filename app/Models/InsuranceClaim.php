<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'claim_number',
        'invoice_id',
        'patient_id',
        'patient_insurance_id',
        'payer_name',
        'policy_number',
        'status',
        'claimed_amount',
        'approved_amount',
        'paid_amount',
        'submitted_at',
        'adjudicated_at',
        'denial_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'claimed_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'adjudicated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PatientInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PatientInvoice::class, 'invoice_id');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<PatientInsurance, $this> */
    public function insurance(): BelongsTo
    {
        return $this->belongsTo(PatientInsurance::class, 'patient_insurance_id');
    }
}

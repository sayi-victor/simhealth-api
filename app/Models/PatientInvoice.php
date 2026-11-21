<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'encounter_id',
        'facility_id',
        'patient_insurance_id',
        'status',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'issued_at',
        'due_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_date' => 'date',
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

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<PatientInsurance, $this> */
    public function insurance(): BelongsTo
    {
        return $this->belongsTo(PatientInsurance::class, 'patient_insurance_id');
    }

    /** @return HasMany<InvoiceLineItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id');
    }

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class, 'invoice_id')->orderByDesc('paid_at');
    }

    /** @return HasMany<InsuranceClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'invoice_id')->orderByDesc('created_at');
    }
}

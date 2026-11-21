<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDispensation extends Model
{
    protected $fillable = [
        'prescription_id',
        'patient_id',
        'drug_id',
        'drug_lot_id',
        'dispensed_by',
        'quantity',
        'unit_price',
        'total_price',
        'dispensed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'dispensed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Prescription, $this> */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** @return BelongsTo<Drug, $this> */
    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    /** @return BelongsTo<DrugLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(DrugLot::class, 'drug_lot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}

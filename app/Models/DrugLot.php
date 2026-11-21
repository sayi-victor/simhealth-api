<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrugLot extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'drug_id',
        'facility_id',
        'lot_number',
        'manufacturer',
        'quantity_on_hand',
        'unit_cost',
        'expires_at',
        'received_at',
        'invoice_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'unit_cost' => 'decimal:2',
            'expires_at' => 'date',
            'received_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Drug, $this> */
    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}

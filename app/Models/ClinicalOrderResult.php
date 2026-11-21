<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalOrderResult extends Model
{
    protected $fillable = [
        'clinical_order_id',
        'clinical_order_item_id',
        'result_code',
        'result_label',
        'value',
        'units',
        'reference_range',
        'abnormal_flag',
        'findings',
        'impression',
        'comments',
        'result_status',
        'document_id',
        'resulted_by',
        'resulted_at',
    ];

    protected function casts(): array
    {
        return [
            'resulted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ClinicalOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class, 'clinical_order_id');
    }

    /** @return BelongsTo<ClinicalOrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrderItem::class, 'clinical_order_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resulter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by');
    }
}

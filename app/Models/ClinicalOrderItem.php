<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalOrderItem extends Model
{
    protected $fillable = [
        'clinical_order_id',
        'catalog_item_id',
        'item_code',
        'item_name',
        'status',
        'notes',
    ];

    /** @return BelongsTo<ClinicalOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class, 'clinical_order_id');
    }

    /** @return BelongsTo<OrderCatalogItem, $this> */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(OrderCatalogItem::class, 'catalog_item_id');
    }

    /** @return HasMany<ClinicalOrderResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ClinicalOrderResult::class);
    }
}

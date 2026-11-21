<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'patient_id',
        'encounter_id',
        'provider_id',
        'facility_id',
        'order_type',
        'priority',
        'status',
        'specimen_type',
        'specimen_number',
        'collected_at',
        'ordered_at',
        'diagnosis',
        'clinical_history',
        'patient_instructions',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'ordered_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ClinicalOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClinicalOrderItem::class);
    }

    /** @return HasMany<ClinicalOrderResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ClinicalOrderResult::class)->orderByDesc('resulted_at');
    }
}

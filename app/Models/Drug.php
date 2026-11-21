<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drug extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'generic_name',
        'rxnorm_code',
        'form',
        'strength',
        'route',
        'unit',
        'unit_price',
        'reorder_level',
        'requires_prescription',
        'is_controlled',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'reorder_level' => 'integer',
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DrugLot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(DrugLot::class)->orderBy('expires_at');
    }

    /** @return HasMany<Prescription, $this> */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }
}

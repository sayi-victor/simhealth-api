<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EncounterForm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'encounter_id',
        'patient_id',
        'form_definition_id',
        'status',
        'data',
        'filled_by',
        'signed_by',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /** @return BelongsTo<FormDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function filler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by');
    }
}

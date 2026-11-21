<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EncounterNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'encounter_id',
        'patient_id',
        'note_type',
        'title',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'body',
        'is_signed',
        'signed_by',
        'signed_at',
        'authored_by',
    ];

    protected function casts(): array
    {
        return [
            'is_signed' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}

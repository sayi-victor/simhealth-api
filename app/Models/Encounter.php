<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encounter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'encounter_number',
        'patient_id',
        'facility_id',
        'billing_facility_id',
        'provider_id',
        'supervisor_id',
        'insurance_id',
        'encounter_date',
        'onset_date',
        'visit_category',
        'sensitivity',
        'status',
        'shift',
        'room_number',
        'reason',
        'referral_source',
        'nhis_vcode',
        'price_level',
        'billing_note',
        'signed_by',
        'signed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'encounter_date' => 'datetime',
            'onset_date' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /** @return BelongsTo<PatientInsurance, $this> */
    public function insurance(): BelongsTo
    {
        return $this->belongsTo(PatientInsurance::class, 'insurance_id');
    }

    /** @return HasMany<EncounterVital, $this> */
    public function vitals(): HasMany
    {
        return $this->hasMany(EncounterVital::class)->orderByDesc('recorded_at');
    }

    /** @return HasMany<EncounterNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(EncounterNote::class)->orderByDesc('created_at');
    }

    /** @return HasMany<EncounterForm, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(EncounterForm::class)->orderByDesc('created_at');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

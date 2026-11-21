<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'mrn',
        'facility_id',
        'primary_provider_id',
        'referring_provider_id',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'sex',
        'marital_status',
        'nationality',
        'religion',
        'language',
        'race',
        'ethnicity',
        'occupation',
        'national_id',
        'passport_number',
        'drivers_license',
        'phone_cell',
        'phone_home',
        'phone_work',
        'email',
        'street',
        'city',
        'state',
        'postal_code',
        'country_code',
        'registration_date',
        'registration_type',
        'referral_source',
        'price_level',
        'billing_note',
        'clinical_alert',
        'deceased_at',
        'deceased_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'registration_date' => 'date',
            'deceased_at' => 'datetime',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            return trim(implode(' ', array_filter([
                $this->title,
                $this->first_name,
                $this->middle_name,
                $this->last_name,
            ])));
        });
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<User, $this> */
    public function primaryProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referringProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PatientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    /** @return HasOne<PatientConsent, $this> */
    public function consent(): HasOne
    {
        return $this->hasOne(PatientConsent::class);
    }

    /** @return HasMany<PatientEmployer, $this> */
    public function employers(): HasMany
    {
        return $this->hasMany(PatientEmployer::class);
    }

    /** @return HasMany<PatientInsurance, $this> */
    public function insurances(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    /** @return HasMany<PatientIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(PatientIssue::class);
    }

    /** @return HasOne<PatientHistory, $this> */
    public function history(): HasOne
    {
        return $this->hasOne(PatientHistory::class);
    }

    /** @return HasMany<Encounter, $this> */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->orderByDesc('encounter_date');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

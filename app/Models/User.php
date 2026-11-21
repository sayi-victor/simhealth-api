<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'facility_id',
        'username',
        'email',
        'password',
        'first_name',
        'middle_name',
        'last_name',
        'title',
        'suffix',
        'department',
        'phone',
        'is_provider',
        'show_in_calendar',
        'is_active',
        'last_login_at',
        'password_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_expires_at' => 'date',
            'password' => 'hashed',
            'is_provider' => 'boolean',
            'show_in_calendar' => 'boolean',
            'is_active' => 'boolean',
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
                $this->suffix,
            ])));
        });
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return HasOne<ProviderProfile, $this> */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /** @return HasMany<ProviderSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(ProviderSchedule::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }
}

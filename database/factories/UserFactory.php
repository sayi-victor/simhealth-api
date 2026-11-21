<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'title' => null,
            'suffix' => null,
            'department' => 'General Medicine',
            'phone' => fake()->phoneNumber(),
            'is_provider' => false,
            'show_in_calendar' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function provider(): static
    {
        return $this->state(fn () => [
            'title' => 'Dr.',
            'is_provider' => true,
            'show_in_calendar' => true,
        ]);
    }
}

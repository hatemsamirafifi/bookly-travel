<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'traveler',
            'locale' => 'en',
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a traveler.
     */
    public function traveler(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'traveler',
        ]);
    }

    /**
     * Indicate that the user is a partner.
     */
    public function partner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'partner',
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Set the user's locale.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }

    /**
     * Indicate that the user is currently locked out.
     */
    public function lockedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_login_count' => 5,
            'locked_until' => now()->addMinutes(1),
        ]);
    }
}

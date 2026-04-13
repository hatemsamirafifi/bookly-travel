<?php

namespace Database\Factories;

use App\Models\GuestIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestIdentity>
 */
class GuestIdentityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<GuestIdentity>
     */
    protected $model = GuestIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'converted_user_id' => null,
            'anonymized_at' => null,
        ];
    }

    /**
     * Indicate that the guest identity has been converted to a user account.
     */
    public function converted(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'converted_user_id' => $userId,
        ]);
    }

    /**
     * Indicate that the guest identity has been anonymized.
     */
    public function anonymized(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => 'anonymized_' . rand(1000, 9999) . '@example.com',
            'name' => 'Anonymized Guest',
            'phone' => null,
            'anonymized_at' => now(),
        ]);
    }
}

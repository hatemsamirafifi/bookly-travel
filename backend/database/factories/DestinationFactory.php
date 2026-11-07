<?php

namespace Database\Factories;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    protected $model = Destination::class;

    public function definition(): array
    {
        $name = 'Destination ' . $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'description' => $this->faker->sentence(),
            'image_url' => 'https://cdn.example.test/destinations/' . $this->faker->uuid() . '.jpg',
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}

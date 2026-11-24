<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => fake()->name(),
            'phone' => '+963' . fake()->unique()->numerify('9########'),
            'date_of_birth' => fake()->date('Y-m-d', '-20 years'),
            'last_visit_at' => null,
            'no_show_count' => 0,
            'banned_until' => null,
        ];
    }

    public function withTwoNoShows(): static
    {
        return $this->state(fn () => [
            'no_show_count' => 2,
            'banned_until' => null,
        ]);
    }
}
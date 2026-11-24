<?php

namespace Database\Factories;

use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Doctor> */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => UserRole::Doctor,
            ]),
            'specialty' => fake()->randomElement(Specialty::cases()),
            'bio' => fake()->sentence(),
        ];
    }
}
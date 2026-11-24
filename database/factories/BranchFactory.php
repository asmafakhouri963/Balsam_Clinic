<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'فرع ' . fake()->city(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'address' => fake()->streetAddress(),
            'phone' => '+963' . fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
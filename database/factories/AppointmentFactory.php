<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->inRandomOrder()->value('id') ?? 1,
            'doctor_id' => Doctor::query()->inRandomOrder()->value('id') ?? 1,
            'patient_id' => Patient::query()->inRandomOrder()->value('id') ?? 1,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
            'notes' => null,
        ];
    }
}
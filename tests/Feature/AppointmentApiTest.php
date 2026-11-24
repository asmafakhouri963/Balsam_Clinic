<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_appointments(): void
    {
        $response = $this->getJson('/api/appointments');

        $response->assertStatus(401);
    }

    public function test_booking_appointment_validates_required_fields_with_422(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', []);

        $response->assertStatus(422);
    }

    public function test_receptionist_cannot_book_in_another_branch_returns_403(): void
    {
        $damascus = Branch::factory()->create(['code' => 'DAM']);
        $aleppo = Branch::factory()->create(['code' => 'ALP']);

        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $damascus->id,
        ]);

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/appointments', [
            'branch_id' => $aleppo->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->next('Sunday')->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $response->assertStatus(403);
    }
}
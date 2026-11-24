<?php

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BalsamApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Doctor $doctor;

    private Patient $patient;

    private string $scheduledDate;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();

        $this->branch = Branch::factory()->create([
            'name' => 'فرع دمشق',
            'code' => 'DAM',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create([
            'name' => 'د. سامر',
            'role' => UserRole::Doctor,
            'branch_id' => $this->branch->id,
        ]);

        $this->doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'specialty' => Specialty::Dermatology,
        ]);

        $nextSunday = now()->next('Sunday');
        $this->scheduledDate = $nextSunday->toDateString();

        DoctorSchedule::create([
            'doctor_id' => $this->doctor->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => $nextSunday->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '14:00:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->patient = Patient::factory()->create([
            'full_name' => 'ليلى أحمد',
            'phone' => '+963933000111',
            'no_show_count' => 0,
            'banned_until' => null,
        ]);
    }

    private function createBookedAppointment(): Appointment
    {
        return app(AppointmentService::class)->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'notes' => 'استشارة أولية',
        ]);
    }

    public function test_guest_cannot_access_appointments(): void
    {
        $response = $this->getJson('/api/appointments');

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_guest_cannot_create_appointment(): void
    {
        $response = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_staff_can_view_available_slots(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->getJson("/api/availability?doctor_id={$this->doctor->id}&branch_id={$this->branch->id}&date={$this->scheduledDate}");

        $response->assertOk()
            ->assertJsonStructure([
                'available_slots',
            ]);
    }

    public function test_authenticated_staff_can_create_appointment(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($receptionist);

        $response = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'notes' => 'حجز عبر الاختبار',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.status', AppointmentStatus::Booked->value);

        $this->assertDatabaseHas('appointments', [
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'status' => AppointmentStatus::Booked->value,
        ]);
    }

    public function test_receptionist_cannot_book_in_another_branch(): void
    {
        $aleppoBranch = Branch::factory()->create([
            'name' => 'فرع حلب',
            'code' => 'ALP',
            'is_active' => true,
        ]);

        $damascusReceptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($damascusReceptionist);

        $response = $this->postJson('/api/appointments', [
            'branch_id' => $aleppoBranch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_receptionist_cannot_mark_arrival_in_another_branch(): void
    {
        $aleppoBranch = Branch::factory()->create([
            'name' => 'فرع حلب',
            'code' => 'ALP',
            'is_active' => true,
        ]);

        $aleppoAppointment = Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-ALP-9001',
            'branch_id' => $aleppoBranch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $damascusReceptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($damascusReceptionist);

        $response = $this->postJson("/api/appointments/{$aleppoAppointment->id}/arrive");

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_validation_errors_use_unified_json_response(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/appointments', []);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'بيانات الطلب غير صالحة.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_missing_api_route_returns_unified_404_response(): void
    {
        $response = $this->getJson('/api/non-existent-balsam-route');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_wrong_api_http_method_returns_unified_405_response(): void
    {
        $response = $this->putJson('/api/appointments');

        $response->assertStatus(405)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_appointments_list_contains_resource_and_pagination_structure(): void
    {
        $appointment = $this->createBookedAppointment();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/appointments');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $appointment->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'reference_number',
                        'branch_id',
                        'doctor_id',
                        'patient_id',
                        'appointment_date',
                        'start_time',
                        'end_time',
                        'status',
                    ],
                ],
                'links',
                'meta',
            ]);
    }
}
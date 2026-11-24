<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessMissedAppointmentsCommand;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $appointmentService;

    private Branch $branch;

    private Doctor $doctor;

    private Patient $patient;

    private string $scheduledDate;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();

        $this->appointmentService = app(AppointmentService::class);

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
        $this->scheduledDate =$nextSunday->toDateString();

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

    public function test_can_book_appointment_and_generate_reference_number(): void
    {
        $appointment =$this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'notes' => 'استشارة جلدية',
        ], isOnlineBooking: true);

        $this->assertEquals(AppointmentStatus::Booked,$appointment->status);
        $this->assertNotEmpty($appointment->reference_number);
        $this->assertStringStartsWith('BLM-DAM-',$appointment->reference_number);
    }

    public function test_overlapping_appointment_slot_is_rejected(): void
    {
        $this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $secondPatient = Patient::factory()->create([
            'phone' => '+963933000222',
        ]);

        $this->expectException(ValidationException::class);

        $this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $secondPatient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);
    }

    public function test_marking_arrived_updates_status_and_patient_last_visit(): void
    {
        $appointment =$this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ]);

        $arrived = $this->appointmentService->markArrived($appointment);

        $this->assertEquals(AppointmentStatus::Arrived,$arrived->status);
        $this->assertNotNull($this->patient->fresh()->last_visit_at);
    }

    public function test_cancelling_booked_appointment_updates_status(): void
    {
        $appointment =$this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '11:00',
            'end_time' => '11:30',
        ]);

        $cancelled = $this->appointmentService->cancel($appointment);

        $this->assertEquals(AppointmentStatus::Cancelled,$cancelled->status);
    }

    public function test_third_no_show_bans_patient_from_online_booking(): void
    {
        $patientWithTwoAbsences = Patient::factory()->withTwoNoShows()->create([
            'phone' => '+963933000333',
        ]);

        Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-DAM-TEST01',
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $patientWithTwoAbsences->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $this->artisan(ProcessMissedAppointmentsCommand::class)
            ->assertExitCode(0);

        $refreshedPatient =$patientWithTwoAbsences->fresh();
        $this->assertEquals(3,$refreshedPatient->no_show_count);
        $this->assertTrue($refreshedPatient->isBannedFromOnlineBooking());

        $this->expectException(ValidationException::class);

        $this->appointmentService->book([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $refreshedPatient->id,
            'appointment_date' => $this->scheduledDate,
            'start_time' => '12:00',
            'end_time' => '12:30',
        ], isOnlineBooking: true);
    }
}
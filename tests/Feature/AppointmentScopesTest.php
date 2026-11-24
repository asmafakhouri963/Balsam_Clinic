<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Scopes\ReceptionistBranchScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_global_scope_restricts_appointments_to_own_branch(): void
    {
        $damascus = Branch::factory()->create(['name' => 'دمشق', 'code' => 'DAM']);
        $aleppo = Branch::factory()->create(['name' => 'حلب', 'code' => 'ALP']);

        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => $damascus->id,
        ]);

        $doctor = Doctor::factory()->create(['specialty' => Specialty::Cardiology]);
        $patient = Patient::factory()->create();

        $damascusAppt = Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-DAM-0001',
            'branch_id' => $damascus->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $aleppoAppt = Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-ALP-0001',
            'branch_id' => $aleppo->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $this->actingAs($receptionist);

        $visibleIds = Appointment::query()->pluck('id')->all();

        $this->assertContains($damascusAppt->id, $visibleIds);
        $this->assertNotContains($aleppoAppt->id, $visibleIds);
    }

    public function test_receptionist_global_scope_can_be_bypassed_when_needed(): void
    {
        $aleppo = Branch::factory()->create(['name' => 'حلب', 'code' => 'ALP']);
        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'branch_id' => 999,
        ]);

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $aleppoAppt = Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-ALP-0002',
            'branch_id' => $aleppo->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $this->actingAs($receptionist);

        $found = Appointment::withoutGlobalScope(ReceptionistBranchScope::class)
            ->find($aleppoAppt->id);

        $this->assertNotNull($found);
        $this->assertEquals($aleppoAppt->id, $found->id);
    }

    public function test_local_scopes_filter_appointments_accurately(): void
    {
        $branchA = Branch::factory()->create(['code' => 'BR1']);
        $branchB = Branch::factory()->create(['code' => 'BR2']);

        $cardioDoc = Doctor::factory()->create(['specialty' => Specialty::Cardiology]);
        $dentalDoc = Doctor::factory()->create(['specialty' => Specialty::Dentistry]);
        $patient = Patient::factory()->create();

        $targetDate = now()->addDays(2)->toDateString();

        $matching = Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-BR1-0010',
            'branch_id' => $branchA->id,
            'doctor_id' => $cardioDoc->id,
            'patient_id' => $patient->id,
            'appointment_date' => $targetDate,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-BR2-0011',
            'branch_id' => $branchB->id,
            'doctor_id' => $cardioDoc->id,
            'patient_id' => $patient->id,
            'appointment_date' => $targetDate,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        Appointment::withoutGlobalScopes()->create([
            'reference_number' => 'BLM-BR1-0012',
            'branch_id' => $branchA->id,
            'doctor_id' => $dentalDoc->id,
            'patient_id' => $patient->id,
            'appointment_date' => $targetDate,
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $results = Appointment::withoutGlobalScopes()
            ->forBranch($branchA->id)
            ->forDoctor($cardioDoc->id)
            ->forSpecialty(Specialty::Cardiology)
            ->onDate($targetDate)
            ->withStatus(AppointmentStatus::Booked)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($matching->id, $results->first()->id);
    }
}
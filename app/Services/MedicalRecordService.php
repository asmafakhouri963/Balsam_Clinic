<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;

class MedicalRecordService
{
    public function createForAppointment(Appointment $appointment, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($appointment, $data) {
            $record = MedicalRecord::query()->updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'doctor_id' => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'diagnosis' => $data['diagnosis'],
                    'prescription' => $data['prescription'],
                    'notes' => $data['notes'] ?? null,
                ]
            );

            $appointment->update([
                'status' => AppointmentStatus::Completed,
            ]);

            if ($appointment->patient) {
                $appointment->patient->updateQuietly([
                    'last_visit_at' => now(),
                ]);
            }

            return $record->load(['appointment', 'doctor.user', 'patient']);
        });
    }

    public function updateRecord(MedicalRecord $record, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $record->update([
                'diagnosis' => $data['diagnosis'] ?? $record->diagnosis,
                'prescription' => $data['prescription'] ?? $record->prescription,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $record->notes,
            ]);

            return $record->fresh(['appointment', 'doctor.user', 'patient', 'auditLogs.user']);
        });
    }
}
<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Jobs\SendAppointmentSmsJob;
use Illuminate\Support\Facades\DB;

class HandleAppointmentCancelled
{
    public function handle(AppointmentCancelled $event): void
    {
        $appointment = $event->appointment->loadMissing(['patient', 'branch']);
        $patient = $appointment->patient;

        if (! $patient || ! $patient->phone) {
            return;
        }

        $branchName = $appointment->branch?->name ?? '';
        $date = $appointment->appointment_date->toDateString();
        $time = substr((string) $appointment->start_time, 0, 5);
        $ref = $appointment->reference_number;

        $message = "نحيطكم علماً بإلغاء الموعد رقم ({$ref}) في ({$branchName}) بتاريخ {$date} الساعة {$time}.";
        $eventKey = "cancelled:appointment:{$appointment->id}:{$appointment->updated_at?->timestamp}";

        DB::afterCommit(function () use ($patient, $message, $eventKey, $appointment) {
            SendAppointmentSmsJob::dispatch(
                phone: $patient->phone,
                message: $message,
                eventKey: $eventKey,
                appointmentId: $appointment->id,
                patientId: $patient->id,
            )->onQueue('default');
        });
    }
}
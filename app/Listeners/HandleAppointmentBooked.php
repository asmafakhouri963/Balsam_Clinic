<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Jobs\SendAppointmentSmsJob;
use Illuminate\Support\Facades\DB;

class HandleAppointmentBooked
{
    public function handle(AppointmentBooked $event): void
    {
        $appointment = $event->appointment->loadMissing(['patient', 'branch', 'doctor.user']);
        $patient = $appointment->patient;

        if (! $patient || ! $patient->phone) {
            return;
        }

        $branchName = $appointment->branch?->name ?? '';
        $date = $appointment->appointment_date->toDateString();
        $time = substr((string) $appointment->start_time, 0, 5);
        $ref = $appointment->reference_number;

        $message = "تم تأكيد حجز موعدكم في عيادات بلسم ({$branchName}) بتاريخ {$date} الساعة {$time}. الرقم المرجعي: {$ref}";
        $eventKey = "booked:appointment:{$appointment->id}";

        // إرسال الـ Job على طابور الأولوية العالية بعد ثبوت الـ Transaction
        DB::afterCommit(function () use ($patient, $message, $eventKey, $appointment) {
            SendAppointmentSmsJob::dispatch(
                phone: $patient->phone,
                message: $message,
                eventKey: $eventKey,
                appointmentId: $appointment->id,
                patientId: $patient->id,
            )->onQueue('high');
        });
    }
}
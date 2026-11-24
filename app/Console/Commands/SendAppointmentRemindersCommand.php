<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentSmsJob;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'balsam:send-reminders {--date=}';

    protected $description = 'إرسال رسائل تذكير للمواعيد المحجوزة قبل 24 ساعة من موعدها';

    public function handle(): int
    {
        $tz = config('app.timezone', 'Asia/Damascus');
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'), $tz)->toDateString()
            : Carbon::now($tz)->addDay()->toDateString();

        $appointments = Appointment::withoutGlobalScopes()
            ->with(['patient', 'branch', 'doctor.user'])
            ->whereDate('appointment_date', $targetDate)
            ->where('status', AppointmentStatus::Booked->value)
            ->get();

        $dispatchedCount = 0;

        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;
            if (! $patient || ! $patient->phone) {
                continue;
            }

            $branchName = $appointment->branch?->name ?? '';
            $date = $appointment->appointment_date->toDateString();
            $time = substr((string) $appointment->start_time, 0, 5);
            $ref = $appointment->reference_number;

            $message = "تذكير بموعدكم غداً في عيادات بلسم ({$branchName}) بتاريخ {$date} الساعة {$time}. الرقم المرجعي: {$ref}";
            $eventKey = "reminder:24h:appointment:{$appointment->id}:{$date}";

            DB::afterCommit(function () use ($patient, $message, $eventKey, $appointment) {
                SendAppointmentSmsJob::dispatch(
                    phone: $patient->phone,
                    message: $message,
                    eventKey: $eventKey,
                    appointmentId: $appointment->id,
                    patientId: $patient->id,
                )->onQueue('default');
            });

            $dispatchedCount++;
        }

        $this->info("تمت جدولة {$dispatchedCount} رسالة تذكير لتاريخ {$targetDate}.");

        return self::SUCCESS;
    }
}
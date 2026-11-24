<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessMissedAppointmentsCommand extends Command
{
    protected $signature = 'balsam:process-missed-appointments';

    protected $description = 'تحديث المواعيد الفائتة غير المؤكدة إلى no_show وحظر الحجز أونلاين بعد 3 غيابات';

    public function handle(AuditLogService $auditLogService): int
    {
        $tz = config('app.timezone', 'Asia/Damascus');
        $now = Carbon::now($tz);

        $missedAppointments = Appointment::withoutGlobalScopes()
            ->with(['patient'])
            ->where('status', AppointmentStatus::Booked->value)
            ->where(function ($query) use ($now) {
                $query->whereDate('appointment_date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->whereDate('appointment_date', $now->toDateString())
                            ->whereTime('end_time', '<', $now->format('H:i:s'));
                    });
            })
            ->get();

        $processedCount = 0;
        $bannedCount = 0;

        foreach ($missedAppointments as $appointment) {
            DB::transaction(function () use ($appointment, $auditLogService, &$processedCount, &$bannedCount) {
                $lockedAppointment = Appointment::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($appointment->id);

                if (! $lockedAppointment || $lockedAppointment->status !== AppointmentStatus::Booked) {
                    return;
                }

                $lockedAppointment->update([
                    'status' => AppointmentStatus::NoShow,
                ]);

                $patient = $lockedAppointment->patient()->lockForUpdate()->first();
                if ($patient) {
                    $newNoShowCount = (int) $patient->no_show_count + 1;
                    $patient->no_show_count = $newNoShowCount;

                    if ($newNoShowCount >= 3) {
                        $patient->banned_until = now()->addDays(30);
                        $bannedCount++;
                    }

                    $auditLogService->recordModelChanges($patient, ['no_show_count', 'banned_until']);
                    $patient->saveQuietly();
                }

                $processedCount++;
            });
        }

        $this->info("تمت معالجة {$processedCount} موعد فائت، ومنع {$bannedCount} مريض من الحجز أونلاين.");

        return self::SUCCESS;
    }
}
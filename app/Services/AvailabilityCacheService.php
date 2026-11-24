<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;

class AvailabilityCacheService
{
    public function getAvailableSlots(int $doctorId, int $branchId, string $date): array
    {
        $key = $this->cacheKey($doctorId, $branchId, $date);

        return Cache::store('redis')
            ->tags($this->tags($doctorId, $branchId, $date))
            ->remember($key, now()->addMinutes(30), function () use ($doctorId, $branchId, $date) {
                $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

                $schedules = DoctorSchedule::query()
                    ->where('doctor_id', $doctorId)
                    ->where('branch_id', $branchId)
                    ->where('day_of_week', $dayOfWeek)
                    ->get();

                if ($schedules->isEmpty()) {
                    return [];
                }

                $bookedSlots = Appointment::withoutGlobalScopes()
                    ->where('doctor_id', $doctorId)
                    ->where('branch_id', $branchId)
                    ->whereDate('appointment_date', $date)
                    ->whereNotIn('status', [AppointmentStatus::Cancelled->value])
                    ->get(['start_time', 'end_time'])
                    ->map(fn ($a) => substr($a->start_time, 0, 5) . '-' . substr($a->end_time, 0, 5))
                    ->flip();

                $slots = [];

                foreach ($schedules as $schedule) {
                    $duration = max(5, (int) $schedule->slot_duration_minutes);
                    $start = Carbon::parse($date . ' ' . $schedule->start_time);
                    $end = Carbon::parse($date . ' ' . $schedule->end_time);

                    $period = CarbonPeriod::create($start, "{$duration} minutes", $end->copy()->subMinutes($duration));

                    foreach ($period as $slotStart) {
                        $slotEnd = $slotStart->copy()->addMinutes($duration);
                        $label = $slotStart->format('H:i') . '-' . $slotEnd->format('H:i');

                        if (! $bookedSlots->has($label)) {
                            $slots[] = [
                                'start_time' => $slotStart->format('H:i'),
                                'end_time' => $slotEnd->format('H:i'),
                            ];
                        }
                    }
                }

                return $slots;
            });
    }

    public function forgetForAppointmentContext(int $doctorId, int $branchId, string $date): void
    {
        Cache::store('redis')
            ->tags($this->tags($doctorId, $branchId, $date))
            ->flush();
    }

    private function cacheKey(int $doctorId, int $branchId, string $date): string
    {
        return "availability:doctor:{$doctorId}:branch:{$branchId}:date:{$date}";
    }

    private function tags(int $doctorId, int $branchId, string $date): array
    {
        return [
            'availability',
            "doctor:{$doctorId}",
            "branch:{$branchId}",
            "date:{$date}",
        ];
    }
}
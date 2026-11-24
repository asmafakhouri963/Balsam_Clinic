<?php

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Services\AvailabilityCacheService;
use Illuminate\Support\Facades\DB;

class AppointmentObserver
{
    private const CACHE_AFFECTING_FIELDS = [
        'branch_id',
        'doctor_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
    ];

    public function __construct(
        private readonly AvailabilityCacheService $cacheService,
    ) {}

    public function creating(Appointment $appointment): void
    {
        if (empty($appointment->status)) {
            $appointment->status = AppointmentStatus::Booked;
        }

        if (empty($appointment->reference_number)) {
            $appointment->reference_number = $this->generateUniqueReferenceNumber($appointment);
        }
    }

    public function created(Appointment $appointment): void
    {
        $this->flushCacheAfterCommit(
            (int) $appointment->doctor_id,
            (int) $appointment->branch_id,
            $appointment->appointment_date->toDateString()
        );
    }

    public function updated(Appointment $appointment): void
    {
        $changedKeys = array_keys($appointment->getChanges());
        $shouldClearCache = ! empty(array_intersect($changedKeys, self::CACHE_AFFECTING_FIELDS));

        if (! $shouldClearCache) {
            return;
        }

        // مسح الكاش للحالة/التاريخ/الفرع/الطبيب السابق والحالي عند تغير الحقول المؤثرة
        $originalDoctorId = (int) ($appointment->getOriginal('doctor_id') ?? $appointment->doctor_id);
        $originalBranchId = (int) ($appointment->getOriginal('branch_id') ?? $appointment->branch_id);
        $originalDate = $appointment->getOriginal('appointment_date')
            ? \Carbon\Carbon::parse($appointment->getOriginal('appointment_date'))->toDateString()
            : $appointment->appointment_date->toDateString();

        $this->flushCacheAfterCommit($originalDoctorId, $originalBranchId, $originalDate);
        $this->flushCacheAfterCommit(
            (int) $appointment->doctor_id,
            (int) $appointment->branch_id,
            $appointment->appointment_date->toDateString()
        );
    }

    public function deleted(Appointment $appointment): void
    {
        $this->flushCacheAfterCommit(
            (int) $appointment->doctor_id,
            (int) $appointment->branch_id,
            $appointment->appointment_date->toDateString()
        );
    }

    private function generateUniqueReferenceNumber(Appointment $appointment): string
    {
        $branch = Branch::withoutGlobalScopes()->find($appointment->branch_id);
        $branchCode = strtoupper($branch?->code ?? 'BRN');
        $year = $appointment->appointment_date
            ? \Carbon\Carbon::parse($appointment->appointment_date)->format('Y')
            : now()->format('Y');

        $prefix = "-{$year}-{$branchCode}";

        // قفل صفوف المواعيد لنفس الفرع والسنة لتفادي تكرار الرقم المتسلسل في الطلبات المتزامنة
        $latestRef = Appointment::withoutGlobalScopes()
            ->where('reference_number', 'like', "%{$prefix}")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('reference_number');

        $nextSequence = 1;
        if ($latestRef && preg_match('/^(\d+)-/', $latestRef, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT) . $prefix;
            $exists = Appointment::withoutGlobalScopes()
                ->where('reference_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $nextSequence++;
        } while (true);
    }

    private function flushCacheAfterCommit(int $doctorId, int $branchId, string $date): void
    {
        DB::afterCommit(function () use ($doctorId, $branchId, $date) {
            $this->cacheService->forgetForAppointmentContext($doctorId, $branchId, $date);
        });
    }
}
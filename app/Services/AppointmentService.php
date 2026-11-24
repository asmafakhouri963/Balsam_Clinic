<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function book(array $data, bool $isOnlineBooking = true): Appointment
    {
        return DB::transaction(function () use ($data, $isOnlineBooking) {
            $patient = Patient::withoutGlobalScopes()->lockForUpdate()->findOrFail($data['patient_id']);

            if ($isOnlineBooking && $patient->isBannedFromOnlineBooking()) {
                throw ValidationException::withMessages([
                    'patient_id' => ['المريض ممنوع من الحجز أونلاين حالياً بسبب تكرار الغياب.'],
                ]);
            }

            $date = Carbon::parse($data['appointment_date']);
            $dayOfWeek = strtolower($date->englishDayOfWeek);
            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = isset($data['end_time'])
                ? $this->normalizeTime($data['end_time'])
                : Carbon::parse($startTime)->addMinutes(30)->format('H:i:s');

            // التحقق من وجود دوام للطبيب في هذا الفرع وهذا اليوم وهذا الوقت
            $hasSchedule = DoctorSchedule::query()
                ->where('doctor_id', $data['doctor_id'])
                ->where('branch_id', $data['branch_id'])
                ->where('day_of_week', $dayOfWeek)
                ->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime)
                ->exists();

            if (! $hasSchedule) {
                throw ValidationException::withMessages([
                    'doctor_id' => ['الطبيب غير مداوم في هذا الفرع خلال اليوم أو الوقت المحدد.'],
                ]);
            }

            // منع حجز وقت محجوز مسبقاً لنفس الطبيب في نفس التاريخ
            $isSlotTaken = Appointment::withoutGlobalScopes()
                ->where('doctor_id', $data['doctor_id'])
                ->whereDate('appointment_date', $date->toDateString())
                ->where('status', '!=', AppointmentStatus::Cancelled->value)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($isSlotTaken) {
                throw ValidationException::withMessages([
                    'start_time' => ['هذا الوقت محجوز مسبقاً لدى الطبيب.'],
                ]);
            }

            $appointment = Appointment::withoutGlobalScopes()->create([
                'branch_id' => $data['branch_id'],
                'doctor_id' => $data['doctor_id'],
                'patient_id' => $patient->id,
                'appointment_date' => $date->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => AppointmentStatus::Booked,
                'notes' => $data['notes'] ?? null,
            ]);

            // تحديث آخر زيارة للمريض بأمان دون إرجاع الحفظ لنفسه أو إطلاق Observers غير مطلوبة
            $patient->updateQuietly([
                'last_visit_at' => now(),
            ]);

            DB::afterCommit(function () use ($appointment) {
                event(new AppointmentBooked($appointment));
            });

            return $appointment->load(['branch', 'doctor.user', 'patient']);
        });
    }

    public function cancel(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            if ($appointment->status === AppointmentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => ['الموعد ملغى مسبقاً.'],
                ]);
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
            ]);

            DB::afterCommit(function () use ($appointment) {
                event(new AppointmentCancelled($appointment));
            });

            return $appointment->fresh(['branch', 'doctor.user', 'patient']);
        });
    }

    public function markArrived(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $appointment->update([
                'status' => AppointmentStatus::Arrived,
            ]);

            if ($appointment->patient) {
                $appointment->patient->updateQuietly([
                    'last_visit_at' => now(),
                ]);
            }

            return $appointment->fresh(['branch', 'doctor.user', 'patient']);
        });
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
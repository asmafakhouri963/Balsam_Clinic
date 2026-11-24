<?php

namespace App\Jobs;

use App\Contracts\SmsSenderInterface;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $eventKey,
        public readonly ?int $appointmentId = null,
        public readonly ?int $patientId = null,
    ) {}

    public function handle(SmsSenderInterface $smsSender): void
    {
        if (config('services.sms.simulate_failure', false)) {
            throw new \RuntimeException('Simulated SMS delivery failure enabled via configuration.');
        }

        // منع التكرار: إذا تم تسجيل هذا event_key مسبقاً لا نعيد الإرسال
        if (NotificationLog::query()->where('event_key', $this->eventKey)->exists()) {
            return;
        }

        $smsSender->send($this->phone, $this->message, [
            'event_key' => $this->eventKey,
            'appointment_id' => $this->appointmentId,
            'patient_id' => $this->patientId,
        ]);

        NotificationLog::query()->firstOrCreate(
            ['event_key' => $this->eventKey],
            [
                'channel' => 'sms',
                'appointment_id' => $this->appointmentId,
                'patient_id' => $this->patientId,
                'sent_at' => now(),
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('stack')->error('[BALSAM SMS JOB FAILED]', [
            'event_key' => $this->eventKey,
            'phone' => $this->phone,
            'appointment_id' => $this->appointmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
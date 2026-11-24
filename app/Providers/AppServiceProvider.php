<?php

namespace App\Providers;

use App\Contracts\SmsSenderInterface;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Listeners\HandleAppointmentBooked;
use App\Listeners\HandleAppointmentCancelled;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Observers\AppointmentObserver;
use App\Observers\MedicalRecordObserver;
use App\Services\AuditLogService;
use App\Services\AvailabilityCacheService;
use App\Services\GatewaySmsSender;
use App\Services\LogSmsSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AvailabilityCacheService::class);
        $this->app->singleton(AuditLogService::class);

        $this->app->singleton(SmsSenderInterface::class, function ($app) {
            $driver = config('services.sms.driver', 'log');

            if ($driver === 'gateway') {
                return new GatewaySmsSender(
                    endpoint: config('services.sms.endpoint'),
                    apiKey: config('services.sms.key'),
                );
            }

            return new LogSmsSender();
        });
    }

    public function boot(): void
    {
        Appointment::observe(AppointmentObserver::class);
        MedicalRecord::observe(MedicalRecordObserver::class);

        Event::listen(AppointmentBooked::class, HandleAppointmentBooked::class);
        Event::listen(AppointmentCancelled::class, HandleAppointmentCancelled::class);
    }
}
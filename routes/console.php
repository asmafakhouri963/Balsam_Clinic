<?php

use App\Console\Commands\GenerateMonthlyClinicReportCommand;
use App\Console\Commands\ProcessMissedAppointmentsCommand;
use App\Console\Commands\SendAppointmentRemindersCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendAppointmentRemindersCommand::class)
    ->dailyAt('09:00')
    ->timezone(config('app.timezone', 'Asia/Damascus'))
    ->withoutOverlapping();

Schedule::command(ProcessMissedAppointmentsCommand::class)
    ->hourly()
    ->timezone(config('app.timezone', 'Asia/Damascus'))
    ->withoutOverlapping();

Schedule::command(GenerateMonthlyClinicReportCommand::class)
    ->monthlyOn(1, '02:00')
    ->timezone(config('app.timezone', 'Asia/Damascus'))
    ->withoutOverlapping();
<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyReportJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyClinicReportCommand extends Command
{
    protected $signature = 'balsam:generate-monthly-report {--month=}';

    protected $description = 'إرسال مهمة توليد التقرير الشهري للعيادات إلى طابور reports';

    public function handle(): int
    {
        $tz = config('app.timezone', 'Asia/Damascus');
        $month = $this->option('month') ?: Carbon::now($tz)->subMonth()->format('Y-m');

        GenerateMonthlyReportJob::dispatch($month)->onQueue('reports');

        $this->info("تم إرسال مهمة توليد التقرير الشهري للشهر {$month} على طابور reports.");

        return self::SUCCESS;
    }
}
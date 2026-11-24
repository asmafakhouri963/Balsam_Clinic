<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public readonly string $yearMonth,
    ) {}

    public function handle(): void
    {
        $start = Carbon::createFromFormat('Y-m', $this->yearMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $branches = Branch::withoutGlobalScopes()->get();
        $branchSummaries = [];

        foreach ($branches as $branch) {
            $baseQuery = Appointment::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);

            $total = (clone $baseQuery)->count();
            $booked = (clone $baseQuery)->where('status', AppointmentStatus::Booked->value)->count();
            $arrived = (clone $baseQuery)->where('status', AppointmentStatus::Arrived->value)->count();
            $completed = (clone $baseQuery)->where('status', AppointmentStatus::Completed->value)->count();
            $cancelled = (clone $baseQuery)->where('status', AppointmentStatus::Cancelled->value)->count();
            $noShow = (clone $baseQuery)->where('status', AppointmentStatus::NoShow->value)->count();

            $branchSummaries[] = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'total_appointments' => $total,
                'booked' => $booked,
                'arrived' => $arrived,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'no_show' => $noShow,
                'no_show_rate_percent' => $total > 0 ? round(($noShow / $total) * 100, 2) : 0,
            ];
        }

        $reportData = [
            'period' => $this->yearMonth,
            'generated_at' => now()->toIso8601String(),
            'branches' => $branchSummaries,
        ];

        $filePath = "reports/balsam-monthly-report-{$this->yearMonth}.json";
        Storage::disk('local')->put($filePath, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::channel('stack')->info('[BALSAM MONTHLY REPORT GENERATED]', [
            'period' => $this->yearMonth,
            'file' => "storage/app/{$filePath}",
        ]);
    }
}
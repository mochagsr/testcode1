<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\IntegrityCheckLog;
use App\Models\PerformanceProbeLog;
use App\Models\ReportExportTask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OpsHealthController extends Controller
{
    public function index(): View
    {
        $failedJobs = (int) DB::table('failed_jobs')->count();
        $pendingReportTasks = (int) ReportExportTask::query()
            ->whereIn('status', ['queued', 'processing'])
            ->count();
        $pendingApprovals = (int) ApprovalRequest::query()->pending()->count();
        $latestBackup = collect(Storage::disk('local')->files('backups'))
            ->sort()
            ->last();
        $latestIntegrityLog = null;
        $integrityIssueRuns7d = 0;
        if (Schema::hasTable('integrity_check_logs')) {
            $latestIntegrityLog = IntegrityCheckLog::query()
                ->latest('checked_at')
                ->latest('id')
                ->first();
            $integrityIssueRuns7d = (int) IntegrityCheckLog::query()
                ->where('is_ok', false)
                ->where('checked_at', '>=', now()->subDays(7))
                ->count();
        }

        $latestPerformanceProbe = null;
        if (Schema::hasTable('performance_probe_logs')) {
            $latestPerformanceProbe = PerformanceProbeLog::query()
                ->latest('probed_at')
                ->latest('id')
                ->first();
        }

        return view('ops_health.index', [
            'failedJobs' => $failedJobs,
            'pendingReportTasks' => $pendingReportTasks,
            'pendingApprovals' => $pendingApprovals,
            'latestBackup' => $latestBackup,
            'dbConnection' => (string) config('database.default'),
            'appEnv' => (string) config('app.env'),
            'appDebug' => (bool) config('app.debug'),
            'latestIntegrityLog' => $latestIntegrityLog,
            'integrityIssueRuns7d' => $integrityIssueRuns7d,
            'latestPerformanceProbe' => $latestPerformanceProbe,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\IntegrityCheckLog;
use App\Models\PerformanceProbeLog;
use App\Models\ReportExportTask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $backupFiles = collect(Storage::disk('local')->files('backups'))
            ->merge(Storage::disk('local')->files('backups/db'))
            ->sort()
            ->values();
        $latestBackup = $backupFiles->last();
        $latestRestoreDrill = Schema::hasTable('restore_drill_logs')
            ? DB::table('restore_drill_logs')->latest('tested_at')->latest('id')->first()
            : null;
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

        $latestSystemCleanup = $this->latestSystemCleanupSummary();

        return view('ops_health.index', [
            'failedJobs' => $failedJobs,
            'pendingReportTasks' => $pendingReportTasks,
            'pendingApprovals' => $pendingApprovals,
            'latestBackup' => $latestBackup,
            'backupFileCount' => $backupFiles->count(),
            'latestRestoreDrill' => $latestRestoreDrill,
            'dbConnection' => (string) config('database.default'),
            'appEnv' => (string) config('app.env'),
            'appDebug' => (bool) config('app.debug'),
            'latestIntegrityLog' => $latestIntegrityLog,
            'integrityIssueRuns7d' => $integrityIssueRuns7d,
            'latestPerformanceProbe' => $latestPerformanceProbe,
            'latestSystemCleanup' => $latestSystemCleanup,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestSystemCleanupSummary(): ?array
    {
        $files = collect(File::glob(storage_path('app/system-cleanups').DIRECTORY_SEPARATOR.'*.json') ?: [])
            ->sortDesc()
            ->values();

        $latest = $files->first();
        if (! is_string($latest) || $latest === '' || ! File::exists($latest)) {
            return null;
        }

        $decoded = json_decode((string) File::get($latest), true);

        return is_array($decoded) ? $decoded : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\ReportExportTask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

        return view('ops_health.index', [
            'failedJobs' => $failedJobs,
            'pendingReportTasks' => $pendingReportTasks,
            'pendingApprovals' => $pendingApprovals,
            'latestBackup' => $latestBackup,
            'dbConnection' => (string) config('database.default'),
            'appEnv' => (string) config('app.env'),
            'appDebug' => (bool) config('app.debug'),
        ]);
    }
}


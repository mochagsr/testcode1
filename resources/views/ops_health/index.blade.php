@extends('layouts.app')

@section('title', 'Ops Health - PgPOS ERP')

@section('content')
    <style>
        .ops-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }
        .ops-col-6 { grid-column: span 6; }
        .ops-col-12 { grid-column: span 12; }
        @media (max-width: 1024px) {
            .ops-col-6 { grid-column: span 12; }
        }
        .ops-kv th { width: 260px; }
        .ops-metric-ok { color: #166534; font-weight: 700; }
        .ops-metric-bad { color: #b91c1c; font-weight: 700; }
        .ops-json {
            white-space: pre-wrap;
            margin: 0;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 11px;
            background: color-mix(in srgb, var(--surface) 92%, var(--border) 8%);
        }
    </style>

    <div class="ops-grid">
    <div class="card ops-col-12">
        <h1 class="page-title" style="margin:0 0 8px 0;">Ops Health</h1>
        <table class="ops-kv">
            <tbody>
            <tr><th style="width:260px;">Environment</th><td>{{ $appEnv }}</td></tr>
            <tr><th>Debug Mode</th><td>{{ $appDebug ? 'ON' : 'OFF' }}</td></tr>
            <tr><th>DB Connection</th><td>{{ $dbConnection }}</td></tr>
            <tr><th>Failed Jobs</th><td>{{ $failedJobs }}</td></tr>
            <tr><th>Queued Report Tasks</th><td>{{ $pendingReportTasks }}</td></tr>
            <tr><th>Pending Approval</th><td>{{ $pendingApprovals }}</td></tr>
            <tr><th>Latest Backup File</th><td>{{ $latestBackup ?: '-' }}</td></tr>
            <tr><th>Total Backup Files</th><td>{{ number_format((int) $backupFileCount, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card ops-col-6">
        <h3 style="margin-top:0;">Restore Drill</h3>
        @if($latestRestoreDrill)
            <table class="ops-kv">
                <tbody>
                <tr>
                    <th>Status Terakhir</th>
                    <td>
                        <span class="{{ ($latestRestoreDrill->status ?? '') === 'passed' ? 'ops-metric-ok' : 'ops-metric-bad' }}">
                            {{ strtoupper((string) ($latestRestoreDrill->status ?? '-')) }}
                        </span>
                    </td>
                </tr>
                <tr><th>Checked At</th><td>{{ \Illuminate\Support\Carbon::parse((string) $latestRestoreDrill->checked_at)->format('d-m-Y H:i:s') }}</td></tr>
                <tr><th>Backup File</th><td>{{ $latestRestoreDrill->backup_file ?: '-' }}</td></tr>
                <tr><th>Message</th><td>{{ $latestRestoreDrill->message ?: '-' }}</td></tr>
                </tbody>
            </table>
        @else
            <div class="muted">Belum ada restore drill log. Jalankan `php artisan app:db-restore-test`.</div>
        @endif
    </div>

    <div class="card ops-col-6">
        <h3 style="margin-top:0;">Integrity Guardrail</h3>
        @if($latestIntegrityLog)
            @php
                $isOk = (bool) $latestIntegrityLog->is_ok;
                $details = (array) ($latestIntegrityLog->details ?? []);
            @endphp
            <table class="ops-kv">
                <tbody>
                <tr>
                    <th>Status Terakhir</th>
                    <td>
                        <span class="{{ $isOk ? 'ops-metric-ok' : 'ops-metric-bad' }}">
                            {{ $isOk ? 'OK' : 'ANOMALI' }}
                        </span>
                    </td>
                </tr>
                <tr><th>Checked At</th><td>{{ optional($latestIntegrityLog->checked_at)->format('d-m-Y H:i:s') ?: '-' }}</td></tr>
                <tr><th>Mismatch Piutang Customer</th><td>{{ number_format((int) $latestIntegrityLog->customer_mismatch_count, 0, ',', '.') }}</td></tr>
                <tr><th>Mismatch Hutang Supplier</th><td>{{ number_format((int) $latestIntegrityLog->supplier_mismatch_count, 0, ',', '.') }}</td></tr>
                <tr><th>Invalid Link Piutang</th><td>{{ number_format((int) $latestIntegrityLog->invalid_receivable_links, 0, ',', '.') }}</td></tr>
                <tr><th>Invalid Link Hutang</th><td>{{ number_format((int) $latestIntegrityLog->invalid_supplier_links, 0, ',', '.') }}</td></tr>
                <tr><th>Run Bermasalah (7 hari)</th><td>{{ number_format((int) $integrityIssueRuns7d, 0, ',', '.') }}</td></tr>
                </tbody>
            </table>
            @if(!empty($details))
                <div style="margin-top:8px;">
                    <div class="muted" style="margin-bottom:4px;">Contoh detail anomali (sample):</div>
                    <pre class="ops-json">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        @else
            <div class="muted">Belum ada log integrity check. Jalankan `php artisan app:integrity-check`.</div>
        @endif
    </div>

    <div class="card ops-col-6">
        <h3 style="margin-top:0;">Performance Probe (List/Search)</h3>
        @if($latestPerformanceProbe)
            @php($metrics = (array) ($latestPerformanceProbe->metrics ?? []))
            <table class="ops-kv">
                <tbody>
                <tr><th>Probed At</th><td>{{ optional($latestPerformanceProbe->probed_at)->format('d-m-Y H:i:s') ?: '-' }}</td></tr>
                <tr><th>Loops</th><td>{{ number_format((int) $latestPerformanceProbe->loops, 0, ',', '.') }}</td></tr>
                <tr><th>Total Duration</th><td>{{ number_format((int) $latestPerformanceProbe->duration_ms, 0, ',', '.') }} ms</td></tr>
                <tr><th>Avg / Loop</th><td>{{ number_format((int) $latestPerformanceProbe->avg_loop_ms, 0, ',', '.') }} ms</td></tr>
                <tr><th>Search Token</th><td>{{ $latestPerformanceProbe->search_token ?: '-' }}</td></tr>
                </tbody>
            </table>
            @if(!empty($metrics))
                <div style="margin-top:8px;">
                    <div class="muted" style="margin-bottom:4px;">Rata-rata metric query (ms):</div>
                    <pre class="ops-json">{{ json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        @else
            <div class="muted">Belum ada log perf probe. Jalankan `php artisan app:load-test-light --loops=80 --search=ang`.</div>
        @endif
    </div>
    </div>
@endsection

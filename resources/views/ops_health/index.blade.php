@extends('layouts.app')

@section('title', 'Ops Health - PgPOS ERP')

@section('content')
    <div class="card">
        <h1 class="page-title" style="margin:0 0 8px 0;">Ops Health</h1>
        <table>
            <tbody>
            <tr><th style="width:260px;">Environment</th><td>{{ $appEnv }}</td></tr>
            <tr><th>Debug Mode</th><td>{{ $appDebug ? 'ON' : 'OFF' }}</td></tr>
            <tr><th>DB Connection</th><td>{{ $dbConnection }}</td></tr>
            <tr><th>Failed Jobs</th><td>{{ $failedJobs }}</td></tr>
            <tr><th>Queued Report Tasks</th><td>{{ $pendingReportTasks }}</td></tr>
            <tr><th>Pending Approval</th><td>{{ $pendingApprovals }}</td></tr>
            <tr><th>Latest Backup File</th><td>{{ $latestBackup ?: '-' }}</td></tr>
            </tbody>
        </table>
    </div>
@endsection


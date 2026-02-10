@extends('layouts.app')

@section('title', __('ui.audit_logs_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.audit_logs_title') }}</h1>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('ui.search_audit_logs_placeholder') }}" value="{{ $search }}" style="max-width: 340px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('ui.user') }}</th>
                <th>{{ __('ui.actions') }}</th>
                <th>{{ __('ui.subject') }}</th>
                <th>{{ __('ui.description') }}</th>
                <th>{{ __('ui.ip') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d-m-Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '-' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>
                        {{ class_basename((string) $log->subject_type) }}
                        @if($log->subject_id)
                            #{{ $log->subject_id }}
                        @endif
                    </td>
                    <td>{{ $log->description ?: '-' }}</td>
                    <td>{{ $log->ip_address ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('ui.no_audit_logs') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $logs->links() }}
        </div>
    </div>
@endsection

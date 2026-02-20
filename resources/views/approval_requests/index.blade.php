@extends('layouts.app')

@section('title', 'Approval Request - PgPOS ERP')

@section('content')
    <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h1 class="page-title" style="margin:0;">Approval Request</h1>
        <form method="get" class="flex">
            <select name="status" onchange="this.form.submit()">
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'Semua'] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Module</th>
                <th>Aksi</th>
                <th>Status</th>
                <th>Eksekusi</th>
                <th>Diminta Oleh</th>
                <th>Waktu</th>
                <th>Proses</th>
            </tr>
            </thead>
            <tbody>
            @forelse($requests as $item)
                <tr>
                    <td>#{{ $item->id }}</td>
                    <td>{{ $item->module }}</td>
                    <td>{{ $item->action }}</td>
                    <td>{{ strtoupper($item->status) }}</td>
                    <td>
                        @php($execution = is_array($item->payload) ? ($item->payload['execution'] ?? null) : null)
                        @if(is_array($execution))
                            <span class="badge {{ ($execution['status'] ?? '') === 'success' ? 'success' : (($execution['status'] ?? '') === 'failed' ? 'danger' : 'warning') }}">
                                {{ strtoupper((string) ($execution['status'] ?? '-')) }}
                            </span>
                            @if(!empty($execution['message']))
                                <div class="muted" style="margin-top:4px;max-width:260px;">{{ (string) $execution['message'] }}</div>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $item->requestedBy?->name ?: '-' }}</td>
                    <td>{{ optional($item->created_at)->format('d-m-Y H:i') }}</td>
                    <td>
                        @if($item->status === 'pending')
                            <div class="flex">
                                <form method="post" action="{{ route('approvals.approve', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn">Setuju</button>
                                </form>
                                <form method="post" action="{{ route('approvals.reject', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn secondary">Tolak</button>
                                </form>
                            </div>
                        @elseif($item->status === 'approved')
                            @php($executionStatus = (string) data_get($item->payload, 'execution.status', ''))
                            @if(in_array($executionStatus, ['failed', 'skipped'], true))
                                <form method="post" action="{{ route('approvals.re-execute', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn secondary">Ulangi Eksekusi</button>
                                </form>
                            @else
                                -
                            @endif
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Belum ada approval request.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $requests->links() }}
    </div>
@endsection

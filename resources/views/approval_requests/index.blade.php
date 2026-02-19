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
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Belum ada approval request.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $requests->links() }}
    </div>
@endsection


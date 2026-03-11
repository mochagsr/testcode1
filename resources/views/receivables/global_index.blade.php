@extends('layouts.app')

@section('title', __('receivable.global_page_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('receivable.global_page_title') }}</h1>

    <div class="card">
        <form method="get" class="flex" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap;">
            <div class="flex" style="gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div>
                    <label>{{ __('receivable.customer') }}</label>
                    <select name="customer_id" style="max-width: 260px;">
                        <option value="0">{{ __('receivable.all_customers') }}</option>
                        @foreach($customerOptions as $customerOption)
                            <option value="{{ $customerOption['id'] }}" @selected($selectedCustomerId === $customerOption['id'])>{{ $customerOption['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ __('receivable.global_status') }}</label>
                    <select name="status" style="max-width: 180px;">
                        <option value="all" @selected($selectedStatus === 'all')>{{ __('receivable.global_status_all') }}</option>
                        <option value="outstanding" @selected($selectedStatus === 'outstanding')>{{ __('receivable.global_status_outstanding') }}</option>
                        <option value="paid" @selected($selectedStatus === 'paid')>{{ __('receivable.global_status_paid') }}</option>
                        <option value="credit" @selected($selectedStatus === 'credit')>{{ __('receivable.global_status_credit') }}</option>
                    </select>
                </div>
                <div>
                    <label>{{ __('txn.search') }}</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('receivable.global_search_placeholder') }}" style="max-width: 280px;">
                </div>
                <button type="submit">{{ __('txn.search') }}</button>
            </div>

            <div class="flex" style="gap:8px;">
                <a class="btn info-btn" target="_blank" href="{{ route('receivables.global.print', request()->query()) }}">{{ __('txn.print') }}</a>
                <a class="btn info-btn" target="_blank" href="{{ route('receivables.global.export.pdf', request()->query()) }}">{{ __('txn.pdf') }}</a>
                <a class="btn info-btn" href="{{ route('receivables.global.export.excel', request()->query()) }}">{{ __('txn.excel') }}</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="overflow:auto;">
            <table>
                <colgroup>
                    <col style="width: 52px;">
                    <col style="width: 220px;">
                    <col style="width: 140px;">
                    <col style="width: 360px;">
                    @foreach($semesterCodes as $semesterCode)
                        <col style="width: 150px;">
                    @endforeach
                    <col style="width: 160px;">
                </colgroup>
                <thead>
                <tr>
                    <th rowspan="2">{{ __('report.columns.no') }}</th>
                    <th rowspan="2">{{ __('receivable.semester_customer_name') }}</th>
                    <th rowspan="2">{{ __('receivable.city') }}</th>
                    <th rowspan="2">{{ __('txn.address') }}</th>
                    <th colspan="{{ max(1, count($semesterHeaders)) }}">{{ __('receivable.semester_receivable') }}</th>
                    <th rowspan="2">{{ __('receivable.outstanding') }}</th>
                </tr>
                <tr>
                    @forelse($semesterHeaders as $semesterHeader)
                        <th>{{ $semesterHeader }}</th>
                    @empty
                        <th>{{ __('report.columns.semester') }}</th>
                    @endforelse
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $index => $row)
                    <tr>
                        <td>{{ (($paginator?->firstItem()) ?? 1) + $index }}</td>
                        <td><a href="{{ route('receivables.index', ['customer_id' => $row['id']]) }}">{{ strtoupper($row['name']) }}</a></td>
                        <td>{{ strtoupper($row['city']) }}</td>
                        <td>{{ $row['address'] }}</td>
                        @foreach($semesterCodes as $semesterCode)
                            <td class="num">Rp {{ number_format((int) ($row['semester_totals'][$semesterCode] ?? 0), 0, ',', '.') }}</td>
                        @endforeach
                        <td class="num">Rp {{ number_format((int) ($row['total_outstanding'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + count($semesterCodes) }}" class="muted">{{ __('receivable.global_no_data') }}</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr style="font-weight:700; background:#2f74c8; color:#fff;">
                    <td colspan="4">{{ __('receivable.semester_total') }}</td>
                    @foreach($semesterCodes as $semesterCode)
                        <td class="num">Rp {{ number_format((int) ($totals['per_semester'][$semesterCode] ?? 0), 0, ',', '.') }}</td>
                    @endforeach
                    <td class="num">Rp {{ number_format((int) ($totals['grand_total'] ?? 0), 0, ',', '.') }}</td>
                </tr>
                </tfoot>
            </table>
        </div>

        @if($paginator)
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; flex-wrap:wrap;">
                <div class="muted">{{ __('receivable.global_total_items', ['count' => $totalItems]) }}</div>
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
@endsection

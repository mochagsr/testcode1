@extends('layouts.app')

@section('title', __('receivable.global_page_title').' - PgPOS ERP')

@section('content')
    <style>
        .receivable-global-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .receivable-global-toolbar .toolbar-left,
        .receivable-global-toolbar .toolbar-right {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .receivable-global-toolbar .toolbar-left {
            flex: 1 1 720px;
        }
        .receivable-global-toolbar .toolbar-right {
            flex: 1 1 260px;
            justify-content: flex-end;
        }
        .receivable-global-table-wrap {
            overflow-x: auto;
        }
        .receivable-global-table {
            min-width: 1160px;
        }
        @media (max-width: 1400px) {
            .receivable-global-toolbar .toolbar-left,
            .receivable-global-toolbar .toolbar-right {
                flex: 1 1 100%;
            }
            .receivable-global-toolbar .toolbar-right {
                justify-content: flex-start;
            }
        }
    </style>
    <div class="muted" style="font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:2px;">List</div>
    <h1 class="page-title">{{ __('receivable.global_page_title') }}</h1>

    <div class="card">
        <form method="get" class="receivable-global-toolbar">
            <div class="toolbar-left">
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
                <button type="submit" class="btn edit-btn">{{ __('txn.search') }}</button>
            </div>

            <div class="toolbar-right">
                <a class="btn info-btn" target="_blank" href="{{ route('receivables.global.print', request()->query()) }}">
                    {{ $selectedCustomerId > 0 ? 'Print Invoice' : __('txn.print') }}
                </a>
                <a class="btn danger-btn" target="_blank" href="{{ route('receivables.global.export.pdf', request()->query()) }}">
                    {{ $selectedCustomerId > 0 ? 'Export Invoice PDF' : 'Export PDF' }}
                </a>
                <a class="btn payment-btn" href="{{ route('receivables.global.export.excel', request()->query()) }}">
                    {{ $selectedCustomerId > 0 ? 'Export Invoice Excel' : 'Export Excel' }}
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="receivable-global-table-wrap">
            <table class="receivable-global-table">
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
                    <th>{{ __('report.columns.no') }}</th>
                    <th>{{ __('receivable.semester_customer_name') }}</th>
                    <th>{{ __('receivable.city') }}</th>
                    <th>{{ __('txn.address') }}</th>
                    @forelse($semesterHeaders as $semesterHeader)
                        <th>{{ $semesterHeader }}</th>
                    @empty
                        <th>{{ __('report.columns.semester') }}</th>
                    @endforelse
                    <th>{{ __('receivable.outstanding') }}</th>
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


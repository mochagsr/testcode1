@extends('layouts.app')

@section('title', __('receivable.semester_page_title').' - PgPOS ERP')

@section('content')
    <style>
        .receivable-semester-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .receivable-semester-toolbar .toolbar-left,
        .receivable-semester-toolbar .toolbar-right {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .receivable-semester-toolbar .toolbar-left {
            flex: 1 1 720px;
        }
        .receivable-semester-toolbar .toolbar-right {
            flex: 1 1 240px;
            justify-content: flex-end;
        }
        .receivable-semester-table-wrap {
            overflow-x: auto;
        }
        .receivable-semester-table {
            min-width: 1120px;
        }
        @media (max-width: 1400px) {
            .receivable-semester-toolbar .toolbar-left,
            .receivable-semester-toolbar .toolbar-right {
                flex: 1 1 100%;
            }
            .receivable-semester-toolbar .toolbar-right {
                justify-content: flex-start;
            }
        }
    </style>
    <h1 class="page-title">{{ __('receivable.semester_page_title') }}</h1>

    <div class="card">
        <form method="get" class="receivable-semester-toolbar">
            <div class="toolbar-left">
                <div>
                    <label>{{ __('receivable.semester_filter') }}</label>
                    <select name="semester" style="max-width: 220px;">
                        @foreach($semesterOptions as $semester)
                            <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ __('receivable.semester_status') }}</label>
                    <select name="status" style="max-width: 160px;">
                        <option value="all" @selected($selectedStatus === 'all')>{{ __('receivable.semester_status_all') }}</option>
                        <option value="outstanding" @selected($selectedStatus === 'outstanding')>{{ __('receivable.semester_status_outstanding') }}</option>
                        <option value="paid" @selected($selectedStatus === 'paid')>{{ __('receivable.semester_status_paid') }}</option>
                        <option value="credit" @selected($selectedStatus === 'credit')>{{ __('receivable.semester_status_credit') }}</option>
                    </select>
                </div>
                <div>
                    <label>{{ __('txn.search') }}</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('receivable.semester_search_placeholder') }}" style="max-width: 280px;">
                </div>
                <button type="submit">{{ __('txn.search') }}</button>
            </div>

            <div class="toolbar-right">
                <a class="btn info-btn" target="_blank" href="{{ route('receivables.semester.print', request()->query()) }}">{{ __('txn.print') }}</a>
                <a class="btn info-btn" target="_blank" href="{{ route('receivables.semester.export.pdf', request()->query()) }}">{{ __('txn.pdf') }}</a>
                <a class="btn info-btn" href="{{ route('receivables.semester.export.excel', request()->query()) }}">Export Excel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="receivable-semester-table-wrap">
            <table class="receivable-semester-table">
                <colgroup>
                    <col style="width: 52px;">
                    <col style="width: 220px;">
                    <col style="width: 120px;">
                    <col style="width: 320px;">
                    <col style="width: 150px;">
                    <col style="width: 150px;">
                    <col style="width: 150px;">
                    <col style="width: 150px;">
                </colgroup>
                <thead>
                <tr>
                    <th>{{ __('report.columns.no') }}</th>
                    <th>{{ __('receivable.semester_customer_name') }}</th>
                    <th>{{ __('receivable.city') }}</th>
                    <th>{{ __('txn.address') }}</th>
                    <th>{{ __('receivable.semester_sales') }}</th>
                    <th>{{ __('receivable.semester_payment') }}</th>
                    <th>{{ __('receivable.semester_return') }}</th>
                    <th>{{ __('receivable.semester_receivable') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $index => $row)
                    <tr>
                        <td>{{ (($paginator?->firstItem()) ?? 1) + $index }}</td>
                        <td><a href="{{ route('receivables.index', ['customer_id' => $row['id'], 'semester' => $selectedSemester]) }}">{{ $row['name'] }}</a></td>
                        <td>{{ $row['city'] }}</td>
                        <td>{{ $row['address'] }}</td>
                        <td class="num">Rp {{ number_format((int) $row['sales_total'], 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) $row['payment_total'], 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) $row['return_total'], 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) $row['outstanding_total'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">{{ __('receivable.semester_no_data') }}</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr style="font-weight:700;">
                    <td colspan="4">{{ __('receivable.semester_total') }}</td>
                    <td class="num">Rp {{ number_format((int) ($totals['sales_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) ($totals['payment_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) ($totals['return_total'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) ($totals['outstanding_total'] ?? 0), 0, ',', '.') }}</td>
                </tr>
                </tfoot>
            </table>
        </div>

        @if($paginator)
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; flex-wrap:wrap;">
                <div class="muted">{{ __('receivable.semester_total_items', ['count' => $totalItems]) }}</div>
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
@endsection


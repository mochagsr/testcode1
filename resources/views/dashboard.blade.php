@extends('layouts.app')

@section('title', __('ui.dashboard_title').' - PgPOS ERP')

@section('content')
    @php
        $showAdminDashboard = (auth()->user()?->canAccess('settings.admin') ?? false)
            || ((string) (auth()->user()?->role ?? '') === 'admin');
    @endphp
    <style>
        .dashboard-quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        .dashboard-quick-link {
            display: block;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in srgb, var(--surface) 94%, var(--border) 6%);
            text-decoration: none;
            color: inherit;
        }
        .dashboard-quick-link strong {
            display: block;
            margin-bottom: 6px;
        }
        .dashboard-quick-link small {
            color: var(--muted);
        }
    </style>
    <h1 class="page-title">{{ __('ui.dashboard_title') }}</h1>

    <div class="grid">
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_products') }}</div>
            <div class="stat-value">{{ (int) round($summary['total_products']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_customers') }}</div>
            <div class="stat-value">{{ (int) round($summary['total_customers']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_global_receivable') }}</div>
            <div class="stat-value">Rp {{ number_format((int) round($summary['total_receivable']), 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_supplier_payable') }}</div>
            <div class="stat-value">Rp {{ number_format((int) round($summary['total_supplier_payable'] ?? 0), 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_invoice_this_month') }}</div>
            <div class="stat-value">Rp {{ number_format((int) round($summary['invoice_this_month']), 0, ',', '.') }}</div>
        </div>
        @if($showAdminDashboard)
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_pending_approvals') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['pending_approvals'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_pending_report_tasks') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['pending_report_tasks'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_active_semesters') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['active_semesters'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_closed_semesters') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['closed_semesters'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_locked_customer_semesters') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['locked_customer_semesters'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_locked_supplier_years') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['locked_supplier_years'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">{{ __('ui.dashboard_backup_files') }}</div>
                <div class="stat-value">{{ number_format((int) ($summary['backup_files'] ?? 0), 0, ',', '.') }}</div>
            </div>
        @endif
    </div>

    <div class="row" style="margin-top: 8px;">
        @if($showAdminDashboard)
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_ops_snapshot') }}</h3>
                <table>
                    <tbody>
                    <tr>
                        <th>{{ __('ui.dashboard_latest_backup') }}</th>
                        <td>{{ $opsSnapshot['latestBackup'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.dashboard_latest_restore_drill') }}</th>
                        <td>{{ ($opsSnapshot['latestRestoreStatus'] ?? '-') }} @if(($opsSnapshot['latestRestoreAt'] ?? '-') !== '-')<span class="muted">({{ $opsSnapshot['latestRestoreAt'] }})</span>@endif</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.dashboard_latest_integrity_check') }}</th>
                        <td>{{ ($opsSnapshot['latestIntegrityStatus'] ?? '-') }} @if(($opsSnapshot['latestIntegrityAt'] ?? '-') !== '-')<span class="muted">({{ $opsSnapshot['latestIntegrityAt'] }})</span>@endif</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.dashboard_latest_performance_probe') }}</th>
                        <td>{{ $opsSnapshot['latestProbeAt'] ?? '-' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        <div class="{{ $showAdminDashboard ? 'col-6' : 'col-12' }}">
            <div class="card">
                <h3>{{ __('ui.dashboard_quick_actions') }}</h3>
                <div class="dashboard-quick-links">
                    @forelse($quickLinks as $quickLink)
                        <a class="dashboard-quick-link" href="{{ $quickLink['route'] }}">
                            <strong>{{ $quickLink['title'] }}</strong>
                            <small>{{ $quickLink['note'] }}</small>
                        </a>
                    @empty
                        <div class="muted">{{ __('ui.no_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($showAdminDashboard)
        <div class="card" style="margin-top: 8px;">
            <h3 style="margin-top:0;">{{ __('ui.dashboard_post_deploy_check_title') }}</h3>
            <div class="muted" style="margin-bottom:8px;">{{ __('ui.dashboard_post_deploy_check_note') }}</div>
            <table>
                <tbody>
                <tr>
                    <th>{{ __('ui.dashboard_post_deploy_backup') }}</th>
                    <td>{{ __('ui.dashboard_post_deploy_backup_note') }}</td>
                </tr>
                <tr>
                    <th>{{ __('ui.dashboard_post_deploy_restore') }}</th>
                    <td>{{ __('ui.dashboard_post_deploy_restore_note') }}</td>
                </tr>
                <tr>
                    <th>{{ __('ui.dashboard_post_deploy_export') }}</th>
                    <td>{{ __('ui.dashboard_post_deploy_export_note') }}</td>
                </tr>
                <tr>
                    <th>{{ __('ui.dashboard_post_deploy_queue') }}</th>
                    <td>{{ __('ui.dashboard_post_deploy_queue_note') }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    @endif

    @if($showAdminDashboard && isset($readyToCloseSemesters) && $readyToCloseSemesters->isNotEmpty())
        <div class="card" style="margin-top: 8px;">
            <h3 style="margin-top:0;">{{ __('receivable.semester_lock_readiness_title') }}</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ __('receivable.semester_filter') }}</th>
                    <th>{{ __('receivable.customer') }}</th>
                    <th>{{ __('receivable.outstanding') }}</th>
                    <th>{{ __('txn.action') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($readyToCloseSemesters as $semesterState)
                    <tr>
                        <td>{{ $semesterState['semester'] ?? '-' }}</td>
                        <td>{{ (int) ($semesterState['paid_customer_count'] ?? 0) }}/{{ (int) ($semesterState['customer_count'] ?? 0) }} {{ __('receivable.customer') }}</td>
                        <td>Rp {{ number_format((int) ($semesterState['total_outstanding'] ?? 0), 0, ',', '.') }}</td>
                        <td>
                            <form method="post" action="{{ route('settings.semester.close') }}">
                                @csrf
                                <input type="hidden" name="semester_period" value="{{ $semesterState['semester'] ?? '' }}">
                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="btn">{{ __('ui.semester_close_button') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="row" style="margin-top: 8px;">
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_uncollected_receivables') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('ui.city') }}</th>
                        <th>{{ __('ui.dashboard_global_receivable') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($uncollectedCustomers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->city ?: '-' }}</td>
                            <td>Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">{{ __('ui.dashboard_no_uncollected_receivables') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($uncollectedCustomers, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $uncollectedCustomers->links() }}
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_pending_order_notes') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.dashboard_order_note_number') }}</th>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('txn.date') }}</th>
                        <th>{{ __('ui.dashboard_order_note_progress') }}</th>
                        <th>{{ __('ui.dashboard_order_note_remaining') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($pendingOrderNotes as $note)
                        @php
                            $orderedTotal = (int) round((float) ($note->ordered_total ?? 0));
                            $fulfilledTotal = (int) round((float) ($note->fulfilled_total ?? 0));
                            $remainingTotal = max(0, (int) round((float) ($note->remaining_total ?? 0)));
                            $progressPercent = $orderedTotal > 0 ? min(100, round(($fulfilledTotal / $orderedTotal) * 100, 2)) : 0;
                            $progressLabel = rtrim(rtrim(number_format($progressPercent, 2, '.', ''), '0'), '.');
                        @endphp
                        <tr>
                            <td><a href="{{ route('order-notes.show', $note->id) }}">{{ $note->note_number }}</a></td>
                            <td>{{ $note->customer_name ?: '-' }}</td>
                            <td>{{ optional($note->note_date)->format('d-m-Y') ?: '-' }}</td>
                            <td>{{ $progressLabel }}%</td>
                            <td>{{ number_format($remainingTotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">{{ __('ui.dashboard_no_pending_order_notes') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($pendingOrderNotes, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $pendingOrderNotes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 8px;">
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_supplier_expense_recap') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.name') }}</th>
                        <th>{{ __('ui.phone') }}</th>
                        <th>{{ __('supplier_payable.outstanding') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($supplierExpenseRecap as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone ?: '-' }}</td>
                            <td>Rp {{ number_format((int) round($supplier->outstanding_payable ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">{{ __('ui.dashboard_no_supplier_expense_recap') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($supplierExpenseRecap, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $supplierExpenseRecap->links() }}
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_low_stock_products') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.code') }}</th>
                        <th>{{ __('ui.category') }}</th>
                        <th>{{ __('ui.name') }}</th>
                        <th>{{ __('ui.stock') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lowStockProducts as $product)
                        <tr>
                            <td>{{ $product->code }}</td>
                            <td>{{ $product->category?->name ?: '-' }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ number_format((int) ($product->stock ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">{{ __('ui.dashboard_no_low_stock_products') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($lowStockProducts, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $lowStockProducts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

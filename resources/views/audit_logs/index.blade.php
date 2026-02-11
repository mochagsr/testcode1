@extends('layouts.app')

@section('title', __('ui.audit_logs_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.audit_logs_title') }}</h1>

    <div class="card">
        <form id="audit-logs-search-form" method="get" class="flex">
            <select id="audit-logs-module-input" name="module" style="max-width: 220px;">
                <option value="">{{ __('ui.audit_module_all') }}</option>
                <option value="sales_invoice" @selected($selectedModule === 'sales_invoice')>{{ __('ui.audit_module_sales_invoice') }}</option>
                <option value="sales_return" @selected($selectedModule === 'sales_return')>{{ __('ui.audit_module_sales_return') }}</option>
                <option value="delivery_note" @selected($selectedModule === 'delivery_note')>{{ __('ui.audit_module_delivery_note') }}</option>
                <option value="order_note" @selected($selectedModule === 'order_note')>{{ __('ui.audit_module_order_note') }}</option>
            </select>
            <input id="audit-logs-date-from-input" type="date" name="date_from" value="{{ $selectedDateFrom }}" style="max-width: 180px;">
            <input id="audit-logs-date-to-input" type="date" name="date_to" value="{{ $selectedDateTo }}" style="max-width: 180px;">
            <input id="audit-logs-search-input" type="text" name="search" placeholder="{{ __('ui.search_audit_logs_placeholder') }}" value="{{ $search }}" style="max-width: 340px;">
            <button type="submit">{{ __('ui.search') }}</button>
            <a
                class="btn secondary"
                href="{{ route('audit-logs.export.csv', ['module' => $selectedModule, 'date_from' => $selectedDateFrom, 'date_to' => $selectedDateTo, 'search' => $search]) }}"
            >
                {{ __('ui.export_audit_csv') }}
            </a>
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
            @php
                if (!function_exists('audit_linkify_codes')) {
                    function audit_linkify_codes(string $text, array $codeLinkMap): string
                    {
                        $chunks = preg_split('/(\b(?:INV|RET|RTN|RTR|SJ|PO|PYT|KWT)-\d{8}-\d{4}\b)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                        if (!is_array($chunks)) {
                            return e($text);
                        }

                        $html = '';
                        foreach ($chunks as $chunk) {
                            $upper = strtoupper($chunk);
                            if (isset($codeLinkMap[$upper])) {
                                $html .= '<a href="'.e((string) $codeLinkMap[$upper]).'" target="_blank" rel="noopener noreferrer">'.e($chunk).'</a>';
                                continue;
                            }
                            $html .= e($chunk);
                        }

                        return $html;
                    }
                }
            @endphp
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d-m-Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '-' }}</td>
                    <td>
                        @php
                            $actionLabel = match ((string) $log->action) {
                                'sales.invoice.create' => __('ui.audit_action_sales_invoice_create'),
                                'sales.invoice.admin_update' => __('ui.audit_action_sales_invoice_admin_update'),
                                'sales.invoice.cancel' => __('ui.audit_action_sales_invoice_cancel'),
                                'sales.return.create' => __('ui.audit_action_sales_return_create'),
                                'sales.return.admin_update' => __('ui.audit_action_sales_return_admin_update'),
                                'sales.return.cancel' => __('ui.audit_action_sales_return_cancel'),
                                'delivery.note.create' => __('ui.audit_action_delivery_note_create'),
                                'delivery.note.admin_update' => __('ui.audit_action_delivery_note_admin_update'),
                                'delivery.note.cancel' => __('ui.audit_action_delivery_note_cancel'),
                                'order.note.create' => __('ui.audit_action_order_note_create'),
                                'order.note.admin_update' => __('ui.audit_action_order_note_admin_update'),
                                'order.note.cancel' => __('ui.audit_action_order_note_cancel'),
                                default => (string) $log->action,
                            };
                        @endphp
                        {{ $actionLabel }}
                    </td>
                    <td>
                        @php
                            $subjectName = (string) ($subjectMap[$log->id] ?? '-');
                            $subjectCode = (string) ($subjectCodeMap[$log->id] ?? '');
                            $subjectLink = $subjectCode !== '' ? ($codeLinkMap[strtoupper($subjectCode)] ?? null) : null;
                        @endphp
                        {{ $subjectName }}
                        @if($subjectCode !== '')
                            :
                            @if($subjectLink)
                                <a href="{{ $subjectLink }}" target="_blank" rel="noopener noreferrer">{{ $subjectCode }}</a>
                            @else
                                {{ $subjectCode }}
                            @endif
                        @endif
                    </td>
                    <td>{!! audit_linkify_codes((string) ($descriptionMap[$log->id] ?? ($log->description ?: '-')), $codeLinkMap ?? []) !!}</td>
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

    <script>
        (function () {
            const form = document.getElementById('audit-logs-search-form');
            const searchInput = document.getElementById('audit-logs-search-input');
            const moduleInput = document.getElementById('audit-logs-module-input');
            const dateFromInput = document.getElementById('audit-logs-date-from-input');
            const dateToInput = document.getElementById('audit-logs-date-to-input');
            if (!form || !searchInput || !moduleInput || !dateFromInput || !dateToInput) {
                return;
            }

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(() => {
                    form.requestSubmit();
                }, 100);
            });

            moduleInput.addEventListener('change', () => {
                form.requestSubmit();
            });
            dateFromInput.addEventListener('change', () => {
                form.requestSubmit();
            });
            dateToInput.addEventListener('change', () => {
                form.requestSubmit();
            });
        })();
    </script>
@endsection

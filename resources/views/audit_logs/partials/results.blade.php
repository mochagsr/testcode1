@if($search !== '' || ($selectedDocumentCode ?? '') !== '' || $selectedModule !== '' || $selectedDateFrom !== '' || $selectedDateTo !== '')
    @php
        $moduleLabels = [
            'sales_invoice' => __('ui.audit_module_sales_invoice'),
            'sales_return' => __('ui.audit_module_sales_return'),
            'delivery_note' => __('ui.audit_module_delivery_note'),
            'order_note' => __('ui.audit_module_order_note'),
            'receivable_payment' => __('ui.audit_module_receivable_payment'),
            'supplier_payment' => __('ui.audit_module_supplier_payment'),
            'outgoing_transaction' => __('ui.audit_module_outgoing_transaction'),
            'delivery_trip' => __('ui.audit_module_delivery_trip'),
            'school_bulk' => __('ui.audit_module_school_bulk'),
            'master' => __('ui.audit_module_master'),
        ];
    @endphp
    <div class="card">
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <span class="muted">{{ __('ui.audit_filter_summary') }}:</span>
            @if($selectedModule !== '')
                <span class="badge info">{{ $moduleLabels[$selectedModule] ?? $selectedModule }}</span>
            @endif
            @if($search !== '')
                <span class="badge neutral">{{ __('ui.audit_filter_search') }}: {{ $search }}</span>
            @endif
            @if(($selectedDocumentCode ?? '') !== '')
                <span class="badge neutral">{{ __('ui.audit_filter_doc_code') }}: {{ $selectedDocumentCode }}</span>
            @endif
            @if($selectedDateFrom !== '' || $selectedDateTo !== '')
                <span class="badge neutral">
                    {{ __('ui.audit_filter_period') }}:
                    {{ $selectedDateFrom !== '' ? \Illuminate\Support\Carbon::parse($selectedDateFrom)->format('d-m-Y') : '...' }}
                    -
                    {{ $selectedDateTo !== '' ? \Illuminate\Support\Carbon::parse($selectedDateTo)->format('d-m-Y') : '...' }}
                </span>
            @endif
        </div>
    </div>
@endif

<div class="card">
    <div class="table-mobile-scroll">
    <table>
        <thead>
        <tr>
            <th>{{ __('txn.date') }}</th>
            <th>{{ __('ui.user') }}</th>
            <th>{{ __('ui.actions') }}</th>
            <th>{{ __('ui.subject') }}</th>
            <th>{{ __('ui.description') }}</th>
            <th>{{ __('ui.audit_changes') }}</th>
            <th>{{ __('ui.ip') }}</th>
            <th>Req ID</th>
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
                        $actionRaw = (string) $log->action;
                        $actionLabel = (string) (($actionLabelMap[$actionRaw] ?? $actionRaw));
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
                <td style="min-width: 280px;">
                    @php
                        $beforeText = (string) (($beforeAfterMap[$log->id]['before'] ?? '-'));
                        $afterText = (string) (($beforeAfterMap[$log->id]['after'] ?? '-'));
                        $hasDiff = $beforeText !== '-' || $afterText !== '-';
                    @endphp
                    @if($hasDiff)
                        <details>
                            <summary>{{ __('ui.audit_view_changes') }}</summary>
                            <div style="margin-top:6px;">
                                <strong style="color:#b91c1c;">{{ __('ui.audit_before') }}:</strong>
                                <pre style="white-space: pre-wrap; margin:4px 0; color:#b91c1c; background:rgba(185,28,28,0.08); border:1px solid rgba(185,28,28,0.2); padding:8px; border-radius:6px;">{{ $beforeText }}</pre>
                                <strong style="color:#166534;">{{ __('ui.audit_after') }}:</strong>
                                <pre style="white-space: pre-wrap; margin:4px 0; color:#166534; background:rgba(22,101,52,0.08); border:1px solid rgba(22,101,52,0.2); padding:8px; border-radius:6px;">{{ $afterText }}</pre>
                            </div>
                        </details>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $log->ip_address ?: '-' }}</td>
                <td>{{ $log->request_id ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">{{ __('ui.no_audit_logs') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top:12px;">
        {{ $logs->links() }}
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>{{ __('txn.date') }}</th>
        <th>{{ __('ui.status') }}</th>
        <th>{{ __('ui.stock') }}</th>
        <th>{{ __('ui.description') }}</th>
        <th>{{ __('ui.user') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($stockMutations as $mutation)
        @php
            $isOut = strtolower((string) $mutation->mutation_type) === 'out';
            $signedQty = ($isOut ? '-' : '+').number_format((int) $mutation->quantity, 0, ',', '.');
            $referenceKey = ((string) ($mutation->reference_type ?? '')).'#'.(int) ($mutation->reference_id ?? 0);
            $reference = $mutationReferenceMap[$referenceKey] ?? null;
            $referenceNumber = $reference['number'] ?? ('#'.(int) ($mutation->reference_id ?? 0));
            $referenceLink = $reference['url'] ?? null;
            $type = (string) ($mutation->reference_type ?? '');
            if ($type === 'App\\Models\\SalesInvoice') {
                $description = $isOut
                    ? __('ui.stock_mutation_desc_sales_invoice_out', ['qty' => $signedQty, 'number' => $referenceNumber])
                    : __('ui.stock_mutation_desc_sales_invoice_in', ['qty' => $signedQty, 'number' => $referenceNumber]);
            } elseif ($type === 'App\\Models\\OutgoingTransaction') {
                $description = $isOut
                    ? __('ui.stock_mutation_desc_outgoing_out', ['qty' => $signedQty, 'number' => $referenceNumber])
                    : __('ui.stock_mutation_desc_outgoing_in', ['qty' => $signedQty, 'number' => $referenceNumber]);
            } elseif ($type === 'App\\Models\\SalesReturn') {
                $description = $isOut
                    ? __('ui.stock_mutation_desc_return_out', ['qty' => $signedQty, 'number' => $referenceNumber])
                    : __('ui.stock_mutation_desc_return_in', ['qty' => $signedQty, 'number' => $referenceNumber]);
            } elseif ($type === 'App\\Models\\Product') {
                $description = $isOut
                    ? __('ui.stock_mutation_desc_manual_out', ['qty' => $signedQty])
                    : __('ui.stock_mutation_desc_manual_in', ['qty' => $signedQty]);
            } else {
                $description = __('ui.stock_mutation_desc_generic', ['qty' => $signedQty]);
            }
            if (!empty($mutation->notes)) {
                $description .= ' - '.(string) $mutation->notes;
            }
        @endphp
        <tr style="background: {{ $isOut ? 'rgba(185, 28, 28, 0.05)' : 'rgba(22, 101, 52, 0.05)' }};">
            <td>{{ optional($mutation->created_at)->format('d-m-Y H:i') }}</td>
            <td>
                <span class="badge {{ $isOut ? 'danger' : 'success' }}">
                    {{ $isOut ? __('ui.stock_mutation_type_out') : __('ui.stock_mutation_type_in') }}
                </span>
            </td>
            <td style="font-weight: 700; color: {{ $isOut ? '#b91c1c' : '#166534' }};">
                {{ $signedQty }}
            </td>
            <td>
                @if($referenceLink)
                    <a href="{{ $referenceLink }}" target="_blank">{{ $referenceNumber }}</a> - {{ $description }}
                @else
                    {{ $description }}
                @endif
            </td>
            <td>{{ $mutation->creator?->name ?: __('txn.system_user') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="muted">{{ __('ui.stock_mutations_empty') }}</td>
        </tr>
    @endforelse
    </tbody>
</table>

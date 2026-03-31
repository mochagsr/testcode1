@php
    $customerFieldId = $customerFieldId ?? 'customer_id';
    $transactionTypeFieldId = $transactionTypeFieldId ?? 'transaction_type';
    $subtypeFieldId = $subtypeFieldId ?? 'customer_printing_subtype_id';
    $selectedSubtypeId = isset($selectedSubtypeId) ? (int) $selectedSubtypeId : 0;
    $selectedSubtypeName = trim((string) ($selectedSubtypeName ?? ''));
    $colClass = $colClass ?? 'col-6';
@endphp

<div class="{{ $colClass }}" id="{{ $subtypeFieldId }}-wrap" data-printing-subtype-wrap>
    <label>{{ __('txn.printing_subtype') }}</label>
    <div style="display:flex; gap:8px; align-items:center;">
        <select
            id="{{ $subtypeFieldId }}"
            name="customer_printing_subtype_id"
            data-printing-subtype-select
            data-customer-field="#{{ $customerFieldId }}"
            data-transaction-type-field="#{{ $transactionTypeFieldId }}"
            data-selected-id="{{ $selectedSubtypeId }}"
            data-selected-name="{{ $selectedSubtypeName }}"
            style="flex:1 1 auto;">
            <option value="">{{ __('txn.printing_subtype_select') }}</option>
            @if($selectedSubtypeId > 0 && $selectedSubtypeName !== '')
                <option value="{{ $selectedSubtypeId }}" selected>{{ $selectedSubtypeName }}</option>
            @endif
        </select>
        <button type="button" class="btn process-soft-btn" data-printing-subtype-add>
            {{ __('txn.printing_subtype_add') }}
        </button>
    </div>
    <div class="muted" style="margin-top:4px;" data-printing-subtype-hint>
        {{ __('txn.printing_subtype_customer_first') }}
    </div>
</div>

@php
    $customerFieldId = $customerFieldId ?? 'customer_id';
    $transactionTypeFieldId = $transactionTypeFieldId ?? 'transaction_type';
    $subtypeFieldId = $subtypeFieldId ?? 'customer_printing_subtype_id';
    $selectedSubtypeId = isset($selectedSubtypeId) ? (int) $selectedSubtypeId : 0;
    $selectedSubtypeName = trim((string) ($selectedSubtypeName ?? ''));
    $colClass = $colClass ?? 'col-6';
    $modalId = $subtypeFieldId . '-modal';
    $overlayId = $subtypeFieldId . '-modal-overlay';
    $nameInputId = $subtypeFieldId . '-new-name';
    $statusId = $subtypeFieldId . '-status';
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

    <div
        id="{{ $overlayId }}"
        data-printing-subtype-modal-overlay
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;">
    </div>
    <div
        id="{{ $modalId }}"
        data-printing-subtype-modal
        style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px;">
            <div style="font-size:1.05rem; font-weight:700;">{{ __('txn.printing_subtype_modal_title') }}</div>
            <button type="button" class="btn info-btn" data-printing-subtype-modal-close style="min-height:30px; padding:4px 10px;">{{ __('txn.cancel') }}</button>
        </div>
        <div class="muted" style="margin-bottom:10px;">{{ __('txn.printing_subtype_modal_hint') }}</div>
        <label for="{{ $nameInputId }}" style="display:block; margin-bottom:6px;">{{ __('txn.printing_subtype') }}</label>
        <input
            type="text"
            id="{{ $nameInputId }}"
            data-printing-subtype-name
            placeholder="{{ __('txn.printing_subtype_modal_placeholder') }}"
            style="width:100%;">
        <div id="{{ $statusId }}" class="muted" data-printing-subtype-modal-status style="margin-top:8px; min-height:20px;"></div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
            <button type="button" class="btn info-btn" data-printing-subtype-modal-close>{{ __('txn.cancel') }}</button>
            <button type="button" class="btn process-btn" data-printing-subtype-save>{{ __('txn.save_changes') }}</button>
        </div>
    </div>
</div>

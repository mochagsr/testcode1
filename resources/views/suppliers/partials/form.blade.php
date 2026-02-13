@php($editing = isset($supplier) && $supplier)
<div class="row">
    <div class="col-6">
        <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
        <input type="text" name="name" value="{{ old('name', $supplier->name ?? '') }}" required>
    </div>
    <div class="col-6">
        <label>{{ __('ui.supplier_company_name') }}</label>
        <input type="text" name="company_name" value="{{ old('company_name', $supplier->company_name ?? '') }}">
    </div>
    <div class="col-6">
        <label>{{ __('ui.phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}">
    </div>
    <div class="col-6">
        <label>{{ __('ui.address') }}</label>
        <input type="text" name="address" value="{{ old('address', $supplier->address ?? '') }}">
    </div>
    <div class="col-12">
        <label>{{ __('ui.notes') }}</label>
        <textarea name="notes" rows="4">{{ old('notes', $supplier->notes ?? '') }}</textarea>
    </div>
</div>

<div class="card">
    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.product_info') }}</h3>
        <p class="form-section-note">{{ __('ui.product_info_note') }}</p>
        <div class="row">
            <div class="col-4">
                <label>{{ __('ui.category') }} <span class="label-required">*</span></label>
                <select name="item_category_id" required>
                    <option value="">{{ __('ui.select_category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('item_category_id', $product?->item_category_id) == $category->id)>
                            {{ $category->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-4">
                <label>{{ __('ui.code') }} <span class="label-required">*</span></label>
                <input type="text" name="code" value="{{ old('code', $product?->code) }}" required>
            </div>
            <div class="col-4">
                <label>{{ __('ui.unit') }}</label>
                <input type="text" name="unit" value="{{ old('unit', $product?->unit) }}">
            </div>
            <div class="col-12">
                <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                <input type="text" name="name" value="{{ old('name', $product?->name) }}" required>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.product_stock_price') }}</h3>
        <p class="form-section-note">{{ __('ui.product_stock_price_note') }}</p>
        <div class="row">
            <div class="col-3">
                <label>{{ __('ui.stock') }} <span class="label-required">*</span></label>
                <input type="number" min="0" name="stock" value="{{ old('stock', $product?->stock ?? 0) }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('ui.price_agent') }} <span class="label-required">*</span></label>
                <input type="number" min="0" step="0.01" name="price_agent" value="{{ old('price_agent', $product?->price_agent ?? 0) }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('ui.price_sales') }} <span class="label-required">*</span></label>
                <input type="number" min="0" step="0.01" name="price_sales" value="{{ old('price_sales', $product?->price_sales ?? 0) }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('ui.price_general') }} <span class="label-required">*</span></label>
                <input type="number" min="0" step="0.01" name="price_general" value="{{ old('price_general', $product?->price_general ?? 0) }}" required>
            </div>
            <div class="col-4">
                <label>{{ __('ui.status') }}</label>
                <select name="is_active">
                    <option value="1" @selected((string) old('is_active', (int) ($product?->is_active ?? true)) === '1')>{{ __('ui.active') }}</option>
                    <option value="0" @selected((string) old('is_active', (int) ($product?->is_active ?? true)) === '0')>{{ __('ui.inactive') }}</option>
                </select>
            </div>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('products.index') }}">{{ __('ui.cancel') }}</a>

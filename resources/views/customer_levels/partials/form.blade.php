<div class="card">
    <div class="row">
        <div class="col-4">
            <label>{{ __('ui.code') }} <span class="label-required">*</span></label>
            <input type="text" name="code" value="{{ old('code', $level?->code) }}" required>
        </div>
        <div class="col-12">
            <label>{{ __('ui.description') }}</label>
            <textarea name="description" rows="3">{{ old('description', $level?->description) }}</textarea>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('customer-levels-web.index') }}">{{ __('ui.cancel') }}</a>

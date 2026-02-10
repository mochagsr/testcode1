<div class="card">
    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.customer_profile') }}</h3>
        <p class="form-section-note">{{ __('ui.customer_profile_note') }}</p>
        <div class="row">
            <div class="col-4">
                <label>{{ __('ui.customer_level') }}</label>
                <select name="customer_level_id">
                    <option value="">{{ __('ui.no_level') }}</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->id }}" @selected(old('customer_level_id', $customer?->customer_level_id) == $level->id)>
                            {{ $level->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-4">
                <label>{{ __('ui.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $customer?->phone) }}">
            </div>
            <div class="col-6">
                <label>{{ __('ui.customer') }} <span class="label-required">*</span></label>
                <input type="text" name="name" value="{{ old('name', $customer?->name) }}" required>
            </div>
            <div class="col-6">
                <label>{{ __('ui.city') }}</label>
                <input type="text" name="city" value="{{ old('city', $customer?->city) }}">
            </div>
            <div class="col-12">
                <label>{{ __('ui.address') }}</label>
                <textarea name="address" rows="2">{{ old('address', $customer?->address) }}</textarea>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.customer_docs') }}</h3>
        <p class="form-section-note">{{ __('ui.customer_docs_note') }}</p>
        <div class="row">
            <div class="col-4">
                <label>{{ __('ui.initial_receivable') }}</label>
                <input type="number" min="0" step="0.01" name="outstanding_receivable" value="{{ old('outstanding_receivable', $customer?->outstanding_receivable ?? 0) }}">
            </div>
            <div class="col-4">
                <label>{{ __('ui.id_card_photo') }}</label>
                <input type="file" name="id_card_photo" accept="image/*">
            </div>
            <div class="col-4">
                @if($customer?->id_card_photo_path)
                    <label>{{ __('ui.current_id_card_photo') }}</label>
                    <div class="flex">
                        <a class="btn secondary" target="_blank" href="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                    </div>
                    <label style="margin-top:8px; display:block;">
                        <input type="checkbox" name="remove_id_card_photo" value="1" style="width:auto;"> {{ __('ui.remove_id_card_photo') }}
                    </label>
                @endif
            </div>
            <div class="col-12">
                <label>{{ __('ui.notes') }}</label>
                <textarea name="notes" rows="2">{{ old('notes', $customer?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('customers-web.index') }}">{{ __('ui.cancel') }}</a>

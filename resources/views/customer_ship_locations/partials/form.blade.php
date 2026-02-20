<div class="card">
    <div class="row inline">
        <div class="col-6">
            <label>{{ __('txn.customer') }} <span class="label-required">*</span></label>
            <select name="customer_id" required>
                <option value="">{{ __('school_bulk.select_customer') }}</option>
                @foreach($customers as $customerOption)
                    <option value="{{ $customerOption->id }}" @selected((int) old('customer_id', $location?->customer_id) === (int) $customerOption->id)>
                        {{ $customerOption->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label>{{ __('school_bulk.school_name') }} <span class="label-required">*</span></label>
            <input type="text" name="school_name" value="{{ old('school_name', $location?->school_name) }}" maxlength="150" required>
        </div>
        <div class="col-6">
            <label>{{ __('txn.recipient_name') }}</label>
            <input type="text" name="recipient_name" value="{{ old('recipient_name', $location?->recipient_name) }}" maxlength="150">
        </div>
        <div class="col-6">
            <label>{{ __('txn.phone') }}</label>
            <input type="text" name="recipient_phone" value="{{ old('recipient_phone', $location?->recipient_phone) }}" maxlength="30">
        </div>
        <div class="col-6">
            <label>{{ __('txn.city') }}</label>
            <input type="text" name="city" value="{{ old('city', $location?->city) }}" maxlength="100">
        </div>
        <div class="col-6">
            <label>{{ __('txn.status') }}</label>
            <select name="is_active">
                <option value="1" @selected((string) old('is_active', $location?->is_active ? '1' : '0') === '1')>{{ __('txn.status_active') }}</option>
                <option value="0" @selected((string) old('is_active', $location?->is_active ? '1' : '0') === '0')>{{ __('school_bulk.status_inactive') }}</option>
            </select>
        </div>
        <div class="col-12">
            <label>{{ __('txn.address') }}</label>
            <textarea name="address" rows="2">{{ old('address', $location?->address) }}</textarea>
        </div>
        <div class="col-12">
            <label>{{ __('txn.notes') }}</label>
            <textarea name="notes" rows="2">{{ old('notes', $location?->notes) }}</textarea>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
<a class="btn secondary" href="{{ route('customer-ship-locations.index') }}">{{ __('txn.cancel') }}</a>


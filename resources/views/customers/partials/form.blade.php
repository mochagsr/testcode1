<div class="card">
    <div class="row inline">
        <div class="col-6">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.customer_profile') }}</h3>
                <p class="form-section-note">{{ __('ui.customer_profile_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.customer_level') }} <span class="label-required">*</span></label>
                        <select name="customer_level_id" required>
                            <option value="">{{ __('ui.select_customer_level') }}</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}" @selected(old('customer_level_id', $customer?->customer_level_id) == $level->id)>
                                    {{ $level->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.customer_name') }} <span class="label-required">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $customer?->name) }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('ui.phone_1') }} <span class="label-required">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone', $customer?->phone) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.phone_2') }}</label>
                        <input type="text" name="phone_secondary" value="{{ old('phone_secondary', $customer?->phone_secondary) }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.city') }} <span class="label-required">*</span></label>
                        <input type="text" name="city" value="{{ old('city', $customer?->city) }}" required>
                    </div>
                    <div class="col-12">
                        <label>{{ __('ui.address') }} <span class="label-required">*</span></label>
                        <textarea name="address" rows="2" required>{{ old('address', $customer?->address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.customer_docs') }}</h3>
                <p class="form-section-note">{{ __('ui.customer_docs_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.initial_receivable') }}</label>
                        <input type="number" min="0" step="1" name="outstanding_receivable" value="{{ old('outstanding_receivable', $customer?->outstanding_receivable ?? 0) }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.id_card_photo') }}</label>
                        <input type="file" name="id_card_photo" accept="image/*">
                    </div>
                    <div class="col-4">
                        @if($customer?->id_card_photo_path)
                            <label>{{ __('ui.current_id_card_photo') }}</label>
                            <div class="flex">
                                <a class="btn info-btn id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                                <a class="btn info-btn" href="{{ route('customers-web.id-card-photo.print', $customer) }}" target="_blank">{{ __('txn.print') }}</a>
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
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('customers-web.index') }}">{{ __('ui.cancel') }}</a>

<div id="id-card-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
    <img id="id-card-modal-image" src="" alt="ID Card" style="max-width:25vw; max-height:25vh; width:auto; height:auto; border:2px solid #fff; border-radius:8px; background:#fff;">
</div>

<script>
    (function () {
        const modal = document.getElementById('id-card-modal');
        const modalImage = document.getElementById('id-card-modal-image');
        const trigger = document.querySelector('.id-card-preview-trigger');
        if (!modal || !modalImage || !trigger) {
            return;
        }

        function closeModal() {
            modal.style.display = 'none';
            modalImage.setAttribute('src', '');
        }

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const image = trigger.getAttribute('data-image');
            if (!image) {
                return;
            }
            modalImage.setAttribute('src', image);
            modal.style.display = 'flex';
        });

        modal.addEventListener('click', closeModal);
        modalImage.addEventListener('click', closeModal);
    })();
</script>

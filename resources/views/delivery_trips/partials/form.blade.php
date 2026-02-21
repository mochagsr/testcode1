@php
    $editing = $trip !== null;
    $tripDateValue = old('trip_date', $prefillDate ?? now()->format('Y-m-d'));
    $driverNameValue = old('driver_name', $trip?->driver_name);
    $vehiclePlateValue = old('vehicle_plate', $trip?->vehicle_plate);
    $fuelCostValue = (int) old('fuel_cost', (int) ($trip?->fuel_cost ?? 0));
    $tollCostValue = (int) old('toll_cost', (int) ($trip?->toll_cost ?? 0));
    $mealCostValue = (int) old('meal_cost', (int) ($trip?->meal_cost ?? 0));
    $otherCostValue = (int) old('other_cost', (int) ($trip?->other_cost ?? 0));
    $notesValue = old('notes', $trip?->notes);
@endphp

<div class="card">
    <div class="form-section">
        <h3 class="form-section-title">{{ __('delivery_trip.trip_header') }}</h3>
        <p class="form-section-note">{{ __('delivery_trip.trip_header_note') }}</p>
        <div class="row inline">
            <div class="col-4">
                <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                <input type="date" name="trip_date" value="{{ $tripDateValue }}" required>
            </div>
            <div class="col-4">
                <label>{{ __('delivery_trip.driver_name') }} <span class="label-required">*</span></label>
                <input type="text" name="driver_name" value="{{ $driverNameValue }}" maxlength="120" required>
            </div>
            <div class="col-4">
                <label>{{ __('delivery_trip.vehicle_plate') }}</label>
                <input type="text" name="vehicle_plate" value="{{ $vehiclePlateValue }}" maxlength="40">
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title">{{ __('delivery_trip.members') }}</h3>
        <p class="form-section-note">{{ __('delivery_trip.members_note') }}</p>
        <div class="row inline">
            <div class="col-6">
                <label>{{ __('delivery_trip.select_members') }}</label>
                <input id="member-filter-input" type="text" placeholder="{{ __('delivery_trip.search_member_placeholder') }}" style="max-width: 260px; margin-bottom: 8px;">
                <div id="member-options" style="max-width: 360px; max-height: 220px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; padding: 8px;">
                    @forelse($users as $user)
                        <label class="member-option" data-name="{{ strtolower($user->name) }}" style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:13px;">
                            <input class="member-checkbox" type="checkbox" name="member_user_ids[]" value="{{ $user->id }}" @checked($selectedUserIds->contains((int) $user->id))>
                            <span>{{ $user->name }} <span class="muted">({{ strtoupper((string) $user->role) }})</span></span>
                        </label>
                    @empty
                        <div class="muted">{{ __('delivery_trip.no_user_option') }}</div>
                    @endforelse
                </div>
                <div class="muted" style="margin-top: 6px;">
                    {{ __('delivery_trip.member_count') }}: <strong id="selected-member-count">0</strong>
                </div>
            </div>
            <div class="col-6">
                <label>{{ __('delivery_trip.extra_members') }}</label>
                <textarea name="extra_member_names" rows="7" placeholder="{{ __('delivery_trip.extra_members_placeholder') }}">{{ $extraMemberNames }}</textarea>
                <div class="muted" style="margin-top: 4px;">{{ __('delivery_trip.extra_members_help') }}</div>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title">{{ __('delivery_trip.cost_breakdown') }}</h3>
        <p class="form-section-note">{{ __('delivery_trip.cost_breakdown_note') }}</p>
        <div class="row inline">
            <div class="col-3">
                <label>{{ __('delivery_trip.fuel_cost') }} <span class="label-required">*</span></label>
                <input class="trip-cost" type="number" min="0" step="1" name="fuel_cost" value="{{ $fuelCostValue }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.toll_cost') }} <span class="label-required">*</span></label>
                <input class="trip-cost" type="number" min="0" step="1" name="toll_cost" value="{{ $tollCostValue }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.meal_cost') }} <span class="label-required">*</span></label>
                <input class="trip-cost" type="number" min="0" step="1" name="meal_cost" value="{{ $mealCostValue }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.other_cost') }} <span class="label-required">*</span></label>
                <input class="trip-cost" type="number" min="0" step="1" name="other_cost" value="{{ $otherCostValue }}" required>
            </div>
            <div class="col-6">
                <label>{{ __('delivery_trip.total_cost') }}</label>
                <input id="trip-total-cost-preview" type="text" value="Rp 0" readonly>
            </div>
            <div class="col-12">
                <label>{{ __('txn.notes') }}</label>
                <textarea name="notes">{{ $notesValue }}</textarea>
            </div>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ $editing ? __('delivery_trip.save_changes') : __('delivery_trip.save') }}</button>
<a class="btn secondary" href="{{ $editing ? route('delivery-trips.show', $trip) : route('delivery-trips.index') }}">{{ __('txn.cancel') }}</a>

<script>
    (function () {
        const memberFilterInput = document.getElementById('member-filter-input');
        const memberOptions = Array.from(document.querySelectorAll('.member-option'));
        const memberCheckboxes = Array.from(document.querySelectorAll('.member-checkbox'));
        const selectedMemberCount = document.getElementById('selected-member-count');

        const syncMemberCount = () => {
            if (!selectedMemberCount) {
                return;
            }
            const checkedCount = memberCheckboxes.filter((input) => input.checked).length;
            selectedMemberCount.textContent = String(checkedCount);
        };

        memberCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncMemberCount));
        syncMemberCount();

        if (memberFilterInput) {
            memberFilterInput.addEventListener('input', () => {
                const term = String(memberFilterInput.value || '').trim().toLowerCase();
                memberOptions.forEach((option) => {
                    const haystack = String(option.getAttribute('data-name') || '');
                    option.style.display = term === '' || haystack.includes(term) ? 'flex' : 'none';
                });
            });
        }

        const costInputs = Array.from(document.querySelectorAll('.trip-cost'));
        const totalPreview = document.getElementById('trip-total-cost-preview');
        const formatRupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

        const syncTotal = () => {
            const total = costInputs.reduce((carry, input) => {
                const value = parseInt(String(input.value || '0'), 10);
                return carry + (Number.isNaN(value) ? 0 : value);
            }, 0);
            if (totalPreview) {
                totalPreview.value = formatRupiah(total);
            }
        };

        costInputs.forEach((input) => input.addEventListener('input', syncTotal));
        syncTotal();
    })();
</script>

@php
    $editing = $trip !== null;
    $tripDateValue = old('trip_date', $prefillDate ?? now()->format('Y-m-d'));
    $driverNameValue = old('driver_name', $trip?->driver_name);
    $assistantNameValue = old('assistant_name', $trip?->assistant_name);
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
            <div class="col-3">
                <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                <input type="date" name="trip_date" value="{{ $tripDateValue }}" required>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.driver_name') }} <span class="label-required">*</span></label>
                <input type="text" name="driver_name" value="{{ $driverNameValue }}" maxlength="120" required>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.assistant_name') }}</label>
                <input type="text" name="assistant_name" value="{{ $assistantNameValue }}" maxlength="120">
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.vehicle_plate') }}</label>
                <input type="text" name="vehicle_plate" value="{{ $vehiclePlateValue }}" maxlength="40">
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

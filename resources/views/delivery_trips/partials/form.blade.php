@php
    $editing = $trip !== null;
    $tripDateValue = old('trip_date', $prefillDate ?? now()->format('Y-m-d'));
    $driverNameValue = old('driver_name', $trip?->driver_name);
    $assistantNameValue = old('assistant_name', $trip?->assistant_name);
    $vehiclePlateValue = old('vehicle_plate', $trip?->vehicle_plate);
    $fuelCostValue = max(0, (int) old('fuel_cost', (int) ($trip?->fuel_cost ?? 0)));
    $tollCostValue = max(0, (int) old('toll_cost', (int) ($trip?->toll_cost ?? 0)));
    $mealCostValue = max(0, (int) old('meal_cost', (int) ($trip?->meal_cost ?? 0)));
    $otherCostValue = max(0, (int) old('other_cost', (int) ($trip?->other_cost ?? 0)));
    $moneyFormat = static fn (int $value): string => $value > 0 ? number_format($value, 0, ',', '.') : '';
    $notesValue = old('notes', $trip?->notes);
@endphp

<style>
    .money-field {
        display: flex;
        align-items: center;
        border: 1px solid var(--border, #c9c9c9);
        border-radius: 8px;
        background: var(--surface, #fff);
        min-height: 40px;
    }
    .money-field .prefix {
        padding: 0 10px;
        border-right: 1px solid var(--border, #c9c9c9);
        color: var(--muted, #666);
        font-weight: 600;
        white-space: nowrap;
    }
    .money-field input {
        border: 0 !important;
        border-radius: 0;
        min-height: 38px;
        width: 100%;
        box-shadow: none !important;
    }
    .money-field input:focus {
        outline: none;
    }
</style>

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
                <label>{{ __('delivery_trip.fuel_cost') }}</label>
                <input type="hidden" name="fuel_cost" value="{{ $fuelCostValue }}">
                <div class="money-field">
                    <span class="prefix">Rp</span>
                    <input class="trip-cost-display" type="text" data-target="fuel_cost" value="{{ $moneyFormat($fuelCostValue) }}" placeholder="0" inputmode="numeric">
                </div>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.toll_cost') }}</label>
                <input type="hidden" name="toll_cost" value="{{ $tollCostValue }}">
                <div class="money-field">
                    <span class="prefix">Rp</span>
                    <input class="trip-cost-display" type="text" data-target="toll_cost" value="{{ $moneyFormat($tollCostValue) }}" placeholder="0" inputmode="numeric">
                </div>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.meal_cost') }}</label>
                <input type="hidden" name="meal_cost" value="{{ $mealCostValue }}">
                <div class="money-field">
                    <span class="prefix">Rp</span>
                    <input class="trip-cost-display" type="text" data-target="meal_cost" value="{{ $moneyFormat($mealCostValue) }}" placeholder="0" inputmode="numeric">
                </div>
            </div>
            <div class="col-3">
                <label>{{ __('delivery_trip.other_cost') }}</label>
                <input type="hidden" name="other_cost" value="{{ $otherCostValue }}">
                <div class="money-field">
                    <span class="prefix">Rp</span>
                    <input class="trip-cost-display" type="text" data-target="other_cost" value="{{ $moneyFormat($otherCostValue) }}" placeholder="0" inputmode="numeric">
                </div>
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
        const costDisplays = Array.from(document.querySelectorAll('.trip-cost-display'));
        const totalPreview = document.getElementById('trip-total-cost-preview');
        const parseDigits = (value) => String(value || '').replace(/\D/g, '');
        const toInt = (value) => {
            const digits = parseDigits(value);
            if (digits === '') {
                return 0;
            }
            const parsed = parseInt(digits, 10);
            return Number.isNaN(parsed) ? 0 : parsed;
        };
        const formatDigits = (value) => {
            const amount = toInt(value);
            if (amount <= 0) {
                return '';
            }
            return amount.toLocaleString('id-ID');
        };
        const formatRupiah = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

        const syncHidden = (displayInput) => {
            const targetName = String(displayInput.dataset.target || '');
            if (targetName === '') {
                return;
            }
            const hidden = document.querySelector(`input[type="hidden"][name="${targetName}"]`);
            if (!hidden) {
                return;
            }
            hidden.value = String(toInt(displayInput.value));
            displayInput.value = formatDigits(displayInput.value);
        };

        const syncTotal = () => {
            const total = costDisplays.reduce((carry, input) => {
                const targetName = String(input.dataset.target || '');
                if (targetName === '') {
                    return carry;
                }
                const hidden = document.querySelector(`input[type="hidden"][name="${targetName}"]`);
                const value = hidden ? parseInt(String(hidden.value || '0'), 10) : 0;
                return carry + (Number.isNaN(value) ? 0 : value);
            }, 0);
            if (totalPreview) {
                totalPreview.value = formatRupiah(total);
            }
        };

        costDisplays.forEach((input) => {
            input.addEventListener('input', () => {
                syncHidden(input);
                syncTotal();
            });
            input.addEventListener('blur', () => {
                syncHidden(input);
                syncTotal();
            });
            syncHidden(input);
        });
        syncTotal();
    })();
</script>

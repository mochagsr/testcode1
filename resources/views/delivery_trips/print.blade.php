<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $trip->trip_number }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'Courier New', monospace; color: #111; font-size: 12px; }
        .company-head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 10px; }
        .company-left { display: flex; gap: 8px; max-width: 66%; }
        .company-logo { width: 37px; height: 50px; border: 1px solid #111; overflow: hidden; flex-shrink: 0; display: grid; place-items: center; }
        .company-logo img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; line-height: 1.2; text-transform: uppercase; }
        .company-detail { white-space: pre-line; font-size: 11px; line-height: 1.3; }
        .doc-title-wrap { text-align: center; }
        .doc-title { font-size: 18px; font-weight: 700; text-transform: uppercase; }
        .doc-number { margin-top: 4px; font-size: 12px; }
        .doc-right { text-align: right; font-size: 11px; white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #111; padding: 5px; vertical-align: top; }
        th { background: #efefef; }
        .meta { margin-top: 8px; }
        .meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .muted { color: #444; }
        .text-right { text-align: right; }
        .signature-wrap { margin-top: 18px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .signature-box { text-align: center; }
        .signature-line { margin-top: 36px; border-top: 1px solid #111; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="{{ !empty($isPdf) ? 'pdf-mode' : '' }}">
@php
    $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
    $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
    $companyAddress = trim((string) \App\Models\AppSetting::getValue('company_address', ''));
    $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
    $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
    $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));

    $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
        ->filter(fn ($line) => trim((string) $line) !== '');

    $companyLogoSrc = null;
    if ($companyLogoPath) {
        $absoluteLogoPath = public_path('storage/' . $companyLogoPath);
        if (is_file($absoluteLogoPath)) {
            $mimeType = mime_content_type($absoluteLogoPath) ?: 'image/png';
            $companyLogoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
        }
    }
@endphp

<div class="no-print" style="margin-bottom:10px;">
    <button onclick="window.print()">{{ __('txn.print') }}</button>
</div>

<div class="company-head">
    <div class="company-left">
        <div class="company-logo">
            @if($companyLogoSrc)
                <img src="{{ $companyLogoSrc }}" alt="Logo">
            @else
                PG
            @endif
        </div>
        <div>
            <div class="company-name">{{ $companyName !== '' ? $companyName : 'CV. PUSTAKA GRAFIKA' }}</div>
            @if($companyDetailLines->isNotEmpty())
                <div class="company-detail">{{ $companyDetailLines->implode("\n") }}</div>
            @endif
        </div>
    </div>
    <div class="doc-title-wrap">
        <div class="doc-title">{{ __('delivery_trip.title') }}</div>
        <div class="doc-number">{{ $trip->trip_number }}</div>
    </div>
    <div class="doc-right">
{{ __('txn.date') }} : {{ optional($trip->trip_date)->format('d-m-Y') }}
{{ __('delivery_trip.driver_name') }} : {{ $trip->driver_name }}
{{ __('delivery_trip.vehicle_plate') }} : {{ $trip->vehicle_plate ?: '-' }}
{{ __('delivery_trip.member_count') }} : {{ (int) $trip->member_count }}
    </div>
</div>

<div class="meta">
    <table>
        <thead>
            <tr>
                <th style="width:50px;">{{ __('txn.no') }}</th>
                <th>{{ __('ui.name') }}</th>
                <th style="width:120px;">{{ __('ui.role') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($trip->members as $member)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $member->member_name }}</td>
                <td>{{ $member->user?->role ? strtoupper((string) $member->user->role) : '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="muted">{{ __('delivery_trip.no_member') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <table style="margin-top: 10px;">
        <tbody>
            <tr><th>{{ __('delivery_trip.fuel_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->fuel_cost, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('delivery_trip.toll_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->toll_cost, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('delivery_trip.meal_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->meal_cost, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('delivery_trip.other_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->other_cost, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('delivery_trip.total_cost') }}</th><td class="text-right"><strong>Rp {{ number_format((int) $trip->total_cost, 0, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    @if(trim((string) $trip->notes) !== '')
        <div style="margin-top: 8px;"><strong>{{ __('txn.notes') }}:</strong> {{ $trip->notes }}</div>
    @endif

    <div class="signature-wrap">
        <div class="signature-box">
            {{ __('delivery_trip.signature_driver') }}
            <div class="signature-line">{{ $trip->driver_name }}</div>
        </div>
        <div class="signature-box">
            {{ __('delivery_trip.signature_admin') }}
            <div class="signature-line">{{ $trip->creator?->name ?: '-' }}</div>
        </div>
    </div>
</div>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $trip->trip_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: 'Courier New', monospace; color: #111; font-size: 11px; line-height: 1.2; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 10px; }
        .company-left { display: flex; gap: 8px; }
        .company-logo { width: 40px; height: 60px; border: none; overflow: hidden; flex-shrink: 0; display: grid; place-items: center; }
        .company-logo img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; line-height: 1.2; text-transform: uppercase; }
        .company-detail { white-space: pre-line; font-size: 11px; line-height: 1.3; }
        .doc-title-wrap { text-align: center; min-width: 210px; align-self: center; }
        .doc-title { font-size: 18px; font-weight: 700; text-transform: uppercase; }
        .doc-number { margin-top: 4px; font-size: 12px; }
        .doc-right { justify-self: end; width: 260px; }
        .doc-meta-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .doc-meta-table th,
        .doc-meta-table td {
            border: none;
            padding: 0 0 2px 0;
            vertical-align: top;
            line-height: 1.25;
            background: transparent;
            font-size: 11px;
        }
        .doc-meta-table th { width: 112px; text-align: left; font-weight: 700; }
        .doc-meta-table td { text-align: left; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #111; padding: 5px; vertical-align: top; }
        th { background: #efefef; }
        .meta { margin-top: 8px; }
        .meta-layout { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; align-items: stretch; }
        .cost-box { grid-column: span 2; }
        .cost-table { margin-top: 0; }
        .cost-table th { width: 62%; }
        .notes-box {
            grid-column: span 2;
            border: 1px solid #111;
            padding: 6px;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .notes-label { font-weight: 700; }
        .notes-content {
            flex: 1;
            white-space: pre-line;
            word-break: break-word;
        }
        .muted { color: #444; }
        .text-right { text-align: right; }
        .signature-wrap { margin-top: 18px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .signature-box { text-align: center; }
        .signature-line { margin-top: 36px; border-top: 1px solid #111; }
        .pdf-mode { font-size: 10px; }
        .pdf-mode .container { max-width: 100%; }
        .pdf-mode .company-head { display: table; width: 100%; table-layout: fixed; border-collapse: collapse; }
        .pdf-mode .company-left,
        .pdf-mode .doc-title-wrap,
        .pdf-mode .doc-right { display: table-cell; vertical-align: top; }
        .pdf-mode .company-left { width: 44%; padding-right: 8px; }
        .pdf-mode .doc-title-wrap { width: 20%; padding: 0 6px; }
        .pdf-mode .doc-right { width: 36%; padding-left: 8px; min-width: 0; }
        .pdf-mode th, .pdf-mode td { padding: 3px; }
        .pdf-mode .doc-meta-table th,
        .pdf-mode .doc-meta-table td { padding: 0 0 1px 0; }
        @media (max-width: 900px) {
            .meta-layout { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cost-box, .notes-box { grid-column: span 2; }
        }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 10px; }
            th, td { padding: 3px; }
        }
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

<div class="container">
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
            <table class="doc-meta-table">
                <tr>
                    <th>{{ __('txn.date') }}</th>
                    <td>: {{ optional($trip->trip_date)->format('d-m-Y') }}</td>
                </tr>
                <tr>
                    <th>{{ __('delivery_trip.driver_name') }}</th>
                    <td>: {{ $trip->driver_name }}</td>
                </tr>
                <tr>
                    <th>{{ __('delivery_trip.assistant_name') }}</th>
                    <td>: {{ $trip->assistant_name ?: '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('delivery_trip.vehicle_plate') }}</th>
                    <td>: {{ $trip->vehicle_plate ?: '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="meta">
        <div class="meta-layout">
            <div class="cost-box">
                <table class="cost-table">
                    <tbody>
                        <tr><th>{{ __('delivery_trip.fuel_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->fuel_cost, 0, ',', '.') }}</td></tr>
                        <tr><th>{{ __('delivery_trip.toll_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->toll_cost, 0, ',', '.') }}</td></tr>
                        <tr><th>{{ __('delivery_trip.meal_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->meal_cost, 0, ',', '.') }}</td></tr>
                        <tr><th>{{ __('delivery_trip.other_cost') }}</th><td class="text-right">Rp {{ number_format((int) $trip->other_cost, 0, ',', '.') }}</td></tr>
                        <tr><th>{{ __('delivery_trip.total_cost') }}</th><td class="text-right"><strong>Rp {{ number_format((int) $trip->total_cost, 0, ',', '.') }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="notes-box">
                <div class="notes-label">{{ __('txn.notes') }}</div>
                <div class="notes-content">{{ trim((string) $trip->notes) !== '' ? $trip->notes : '-' }}</div>
            </div>
        </div>

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
</div>
</body>
</html>


<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $payment->payment_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.3; color: #111; }
        .container { max-width: 760px; margin: 0 auto; }
        .receipt { border: 1px solid #111; padding: 12px; }
        .top { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .company-left { display: flex; gap: 8px; max-width: 66%; }
        .company-logo { width: 34px; height: 34px; border: 1px solid #111; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 14px; font-weight: 700; line-height: 1.2; text-transform: uppercase; }
        .company-detail { white-space: pre-line; }
        .kwitansi-box { text-align: right; min-width: 220px; }
        .kwitansi-title { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
        .canceled-banner { margin: 8px 0 2px; padding: 4px 8px; border: 1px solid #111; text-align: center; font-weight: 700; letter-spacing: 0.6px; }
        .meta { margin-top: 2px; }
        .line { display: flex; margin-bottom: 4px; }
        .line-label { width: 150px; flex-shrink: 0; }
        .line-sep { width: 12px; text-align: center; flex-shrink: 0; }
        .line-value { border-bottom: 1px dotted #111; flex: 1; min-height: 16px; padding: 0 4px; }
        .amount-box { border: 1px solid #111; padding: 8px; margin: 10px 0; display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .amount-label { font-weight: 700; }
        .amount-value { font-size: 16px; font-weight: 700; white-space: nowrap; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
        .sign-box { text-align: center; width: 46%; }
        .sign-box.left { justify-self: start; }
        .sign-box.right { justify-self: end; }
        .sign-space { height: 30px; }
        .sign-name { border-top: 1px solid #111; padding-top: 3px; }
        .muted { color: #333; }
        .pdf-mode { font-size: 10px; }
        .pdf-mode .container { max-width: 100%; }
        .pdf-mode .receipt { padding: 10px; }
        .pdf-mode .company-name { font-size: 13px; }
        .pdf-mode .kwitansi-title { font-size: 16px; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 10px; }
            .receipt { border: 1px solid #111; }
        }
    </style>
</head>
<body class="{{ !empty($isPdf) ? 'pdf-mode' : '' }}">
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = trim((string) \App\Models\AppSetting::getValue('company_address', ''));
        $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
        $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
        $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));
        $printNotes = trim((string) ($payment->notes ?? ''));
        $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();
        $companyLogoSrc = null;

        if ($companyLogoPath) {
            $absoluteLogoPath = public_path('storage/' . $companyLogoPath);
            if (is_file($absoluteLogoPath)) {
                $mimeType = mime_content_type($absoluteLogoPath) ?: 'image/png';
                $companyLogoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
            }
        }
    @endphp
    @if(empty($isPdf))
        <div class="no-print" style="margin-bottom: 10px;">
            <button onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="receipt">
        <div class="top">
            <div class="company-left">
                <div class="company-logo">
                    @if($companyLogoSrc)
                        <img src="{{ $companyLogoSrc }}" alt="Logo" class="company-logo-img">
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
            <div class="kwitansi-box">
                <div class="kwitansi-title">KWITANSI</div>
                <div class="meta">{{ __('txn.no') }}: {{ $payment->payment_number }}</div>
                <div class="meta">{{ __('txn.date') }}: {{ $payment->payment_date?->format('d-m-Y') }}</div>
            </div>
        </div>
        @if($payment->is_canceled)
            <div class="canceled-banner">{{ strtoupper(__('txn.status_canceled')) }}</div>
        @endif

        <div class="line">
            <div class="line-label">{{ __('receivable.customer') }}</div>
            <div class="line-sep">:</div>
            <div class="line-value">{{ $payment->customer?->name ?: '-' }}</div>
        </div>
        <div class="line">
            <div class="line-label">{{ __('txn.address') }}</div>
            <div class="line-sep">:</div>
            <div class="line-value">{{ $payment->customer_address ?: '-' }}</div>
        </div>
        <div class="line">
            <div class="line-label">{{ __('receivable.amount_in_words') }}</div>
            <div class="line-sep">:</div>
            <div class="line-value">{{ $payment->amount_in_words }}</div>
        </div>
        <div class="amount-box">
            <div class="amount-label">{{ __('receivable.amount_paid') }}</div>
            <div class="amount-value">Rp {{ number_format((int) round($payment->amount), 0, ',', '.') }}</div>
        </div>
        <div class="line">
            <div class="line-label">{{ __('txn.notes') }}</div>
            <div class="line-sep">:</div>
            <div class="line-value">{{ $printNotes !== '' ? $printNotes : '-' }}</div>
        </div>

        <div class="signatures">
            <div class="sign-box left">
                <div class="muted">{{ __('receivable.customer_signature') }}</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ $payment->customer_signature }}</div>
            </div>
            <div class="sign-box right">
                <div class="muted">{{ __('txn.signature') }}</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ $payment->user_signature }}</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>



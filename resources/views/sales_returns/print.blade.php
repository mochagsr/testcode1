<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $salesReturn->return_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .company-left { display: flex; gap: 10px; }
        .company-logo { width: 42px; height: 42px; border: 1px solid #111; display: grid; place-items: center; font-size: 18px; font-weight: 700; letter-spacing: 2px; overflow: hidden; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 28px; font-weight: 700; letter-spacing: 1px; margin-bottom: 2px; }
        .company-detail { font-size: 12px; line-height: 1.35; }
        .doc-meta-right { font-size: 13px; line-height: 1.5; min-width: 220px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 16px; }
        .title { font-size: 24px; font-weight: 700; }
        .meta { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #111; padding: 6px; text-align: left; vertical-align: top; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        .signature-name { font-weight: 600; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
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

    <div class="company-head">
        <div class="company-left">
            <div class="company-logo">
                @if($companyLogoSrc)
                    <img src="{{ $companyLogoSrc }}" alt="Logo" class="company-logo-img">
                @else
                    PG
                @endif
            </div>
            <div>
                <div class="company-name">CV. PUSTAKA GRAFIKA</div>
                <div class="company-detail">
                    Jl. Pakeran No. 23, Malang<br>
                    Telp/Fax: (0341) 3031300, 321853<br>
                    Phone: 0851-0009-1288<br>
                    Email: pustakagrafika7@gmail.com
                </div>
            </div>
        </div>
        <div class="doc-meta-right">
            <div><strong>{{ __('txn.date') }}</strong> : {{ $salesReturn->return_date->format('d-m-Y') }}</div>
            <div><strong>{{ __('txn.note_number') }}</strong> : {{ $salesReturn->return_number }}</div>
            <div><strong>{{ __('txn.name') }}</strong> : {{ $salesReturn->customer?->name ?: '-' }}</div>
            <div><strong>{{ __('txn.address') }}</strong> : {{ $salesReturn->customer?->address ?: '-' }}</div>
        </div>
    </div>

    <div class="header">
        <div>
            <div class="title">{{ __('txn.sales_returns_title') }}</div>
            <div>{{ __('txn.no') }}: {{ $salesReturn->return_number }}</div>
        </div>
        <div>
            <div>{{ __('txn.return_date') }}: {{ $salesReturn->return_date->format('d-m-Y') }}</div>
            <div>{{ __('txn.semester_period') }}: {{ $salesReturn->semester_period ?: '-' }}</div>
        </div>
    </div>

    <div class="meta"><strong>{{ __('txn.customer') }}:</strong> {{ $salesReturn->customer?->name ?: '-' }}</div>
    <div class="meta"><strong>{{ __('txn.phone') }}:</strong> {{ $salesReturn->customer?->phone ?: '-' }}</div>
    <div class="meta"><strong>{{ __('txn.city') }}:</strong> {{ $salesReturn->customer?->city ?: '-' }}</div>
    <div class="meta"><strong>{{ __('txn.address') }}:</strong> {{ $salesReturn->customer?->address ?: '-' }}</div>

    <table>
        <thead>
        <tr>
            <th style="width: 6%">{{ __('txn.no') }}</th>
            <th style="width: 14%">{{ __('txn.code') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th style="width: 10%">{{ __('txn.qty') }}</th>
            <th style="width: 18%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($salesReturn->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_code }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ number_format($item->quantity) }}</td>
                <td>{{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="margin-top: 10px;"><strong>{{ __('txn.total_return') }}:</strong> Rp {{ number_format($salesReturn->total, 2) }}</div>
    <div style="margin-top: 6px;"><strong>{{ __('txn.reason') }}:</strong> {{ $salesReturn->reason ?: '-' }}</div>

    <table class="signature-table">
        <tr>
            <th>{{ __('txn.signature_created') }}</th>
            <th>{{ __('txn.signature_checked') }}</th>
            <th>{{ __('txn.signature_sender') }}</th>
            <th>{{ __('txn.signature_receiver') }}</th>
        </tr>
        <tr>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td class="signature-name">{{ auth()->user()->name ?? '________________' }}</td>
            <td>________________</td>
            <td>________________</td>
            <td>________________</td>
        </tr>
    </table>
</div>
</body>
</html>

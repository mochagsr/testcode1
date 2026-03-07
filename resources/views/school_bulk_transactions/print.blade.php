<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('school_bulk.bulk_transaction_title') }} {{ $transaction->transaction_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .school-page { page-break-after: always; break-after: page; padding-bottom: 6px; }
        .school-page:last-child { page-break-after: auto; break-after: auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 44%) minmax(200px, 22%) minmax(0, 34%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 10px; }
        .company-left { display: flex; gap: 8px; min-width: 0; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 1px; line-height: 1.2; text-transform: uppercase; }
        .company-detail { font-size: 11px; line-height: 1.3; white-space: pre-line; }
        .doc-title-center { font-size: 11px; line-height: 1.25; min-width: 210px; text-align: center; align-self: center; min-width: 0; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; min-width: 210px; justify-self: end; width: 100%; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .doc-title { font-size: 18px; font-weight: 700; text-align: center; }
        .doc-number { text-align: center; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #111; padding: 4px; text-align: left; vertical-align: top; }
        th { font-size: 10px; }
        .table-summary { display: grid; grid-template-columns: minmax(0, 1fr) 220px 340px; align-items: flex-start; gap: 16px; margin-top: 12px; }
        .summary-spacer { min-height: 1px; }
        .qty-box { width: 100%; table-layout: fixed; }
        .qty-box table,
        .total-box { margin-top: 0; }
        .qty-box td:first-child { font-weight: 700; background: #f7f7f7; width: 66%; }
        .qty-box td:last-child { width: 34%; text-align: center; font-weight: 700; white-space: nowrap; }
        .total-box { width: 100%; }
        .total-box td { border: 1px solid #111; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        .signature-name { font-weight: 600; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 10px; }
            th, td { padding: 3px; }
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) \App\Models\AppSetting::getValue('company_address', '')), 5);
        $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
        $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
        $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));
        $companyInvoiceNotes = trim((string) \App\Models\AppSetting::getValue('company_invoice_notes', ''));
        $reportHeaderText = trim((string) \App\Models\AppSetting::getValue('report_header_text', ''));
        $printNotes = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($transaction->notes ?: $companyInvoiceNotes)), 4);
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

    @php
        $itemsByLocation = $transaction->items->groupBy(fn($item) => (int) ($item->school_bulk_transaction_location_id ?? 0));
        $overallQty = (int) round((float) $transaction->items->sum('quantity'), 0);
    @endphp
    @forelse($transaction->locations as $location)
        @php
            $locationItems = collect($itemsByLocation->get((int) $location->id, []))->values();
            if ($locationItems->isEmpty()) {
                $locationItems = collect($itemsByLocation->get(0, []))->values();
            }
            $perSchoolSubtotal = (int) $locationItems->sum(function ($item): int {
                return ((int) ($item->quantity ?? 0)) * ((int) ($item->unit_price ?? 0));
            });
            $perSchoolQty = (int) round((float) $locationItems->sum('quantity'), 0);
            $shipAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($location->address ?: ($transaction->customer?->address ?: '')), 5);
            $noteNumber = $transaction->transaction_number . '-' . str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT);
        @endphp
        <section class="school-page">
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
                        <div class="company-name">{{ $companyName !== '' ? $companyName : 'CV. PUSTAKA GRAFIKA' }}</div>
                        @if($companyDetailLines->isNotEmpty())
                            <div class="company-detail">{{ $companyDetailLines->implode("\n") }}</div>
                        @endif
                    </div>
                </div>
                <div class="doc-title-center">
                    <div class="doc-title">{{ $reportHeaderText !== '' ? $reportHeaderText : __('school_bulk.bulk_transaction_title') }}</div>
                    <div class="doc-number">{{ __('txn.no') }}: {{ $noteNumber }}</div>
                </div>
                <div class="doc-meta-right">
                    <div class="meta-line"><strong>{{ __('txn.date') }}</strong><span>:</span><span class="meta-value">{{ optional($transaction->transaction_date)->format('d-m-Y') ?: '-' }}</span></div>
                    <div class="meta-line"><strong>Semester</strong><span>:</span><span class="meta-value">{{ $transaction->semester_period ?: '-' }}</span></div>
                    <div class="meta-line"><strong>{{ __('school_bulk.bill_to') }}</strong><span>:</span><span class="meta-value">{{ $transaction->customer?->name ?: '-' }}</span></div>
                    <div class="meta-line"><strong>{{ __('school_bulk.ship_to') }}</strong><span>:</span><span class="meta-value">{{ $location->school_name ?: '-' }}</span></div>
                    <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $location->recipient_phone ?: ($transaction->customer?->phone ?: '-') }}</span></div>
                    <div class="meta-line"><strong>{{ __('txn.city') }}</strong><span>:</span><span class="meta-value">{{ $location->city ?: ($transaction->customer?->city ?: '-') }}</span></div>
                    <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $shipAddress !== '' ? $shipAddress : '-' }}</span></div>
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th style="width: 6%">{{ __('txn.no') }}</th>
                    <th>{{ __('txn.name') }}</th>
                    <th style="width: 10%">{{ __('txn.qty') }}</th>
                    <th style="width: 12%">{{ __('txn.unit') }}</th>
                    <th style="width: 18%">{{ __('txn.price') }}</th>
                    <th style="width: 20%">{{ __('txn.subtotal') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($locationItems as $item)
                    @php
                        $adjustedQuantity = (int) ($item->quantity ?? 0);
                        $lineTotal = $adjustedQuantity * ((int) ($item->unit_price ?? 0));
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $adjustedQuantity }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>Rp {{ number_format((int) ($item->unit_price ?? 0), 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($lineTotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="table-summary">
                <div class="summary-spacer"></div>
                <div class="qty-box">
                    <table>
                        <tr><td>{{ __('school_bulk.qty_total_per_school') }}</td><td>{{ number_format($perSchoolQty, 0, ',', '.') }}</td></tr>
                        <tr><td>{{ __('school_bulk.qty_total_all_schools') }}</td><td>{{ number_format($overallQty, 0, ',', '.') }}</td></tr>
                    </table>
                </div>
                <table class="total-box">
                    <tr><td>{{ __('txn.sub_total') }}</td><td>Rp {{ number_format($perSchoolSubtotal, 0, ',', '.') }}</td></tr>
                    <tr><td>{{ __('txn.discount') }}</td><td>Rp 0</td></tr>
                    <tr><td><strong>{{ __('txn.grand_total') }}</strong></td><td><strong>Rp {{ number_format($perSchoolSubtotal, 0, ',', '.') }}</strong></td></tr>
                </table>
            </div>

            <div style="margin-top: 10px; white-space: pre-line;"><strong>{{ __('txn.notes') }}:</strong> {{ $printNotes !== '' ? $printNotes : '-' }}</div>

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

        </section>
    @empty
        <section class="school-page">
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
                        <div class="company-name">{{ $companyName !== '' ? $companyName : 'CV. PUSTAKA GRAFIKA' }}</div>
                        @if($companyDetailLines->isNotEmpty())
                            <div class="company-detail">{{ $companyDetailLines->implode("\n") }}</div>
                        @endif
                    </div>
                </div>
                <div class="doc-title-center">
                    <div class="doc-title">{{ $reportHeaderText !== '' ? $reportHeaderText : __('school_bulk.bulk_transaction_title') }}</div>
                    <div class="doc-number">{{ __('school_bulk.transaction_number') }}: {{ $transaction->transaction_number }}</div>
                </div>
                <div class="doc-meta-right">
                    <div><strong>{{ __('txn.date') }}</strong> : {{ optional($transaction->transaction_date)->format('d-m-Y') ?: '-' }}</div>
                </div>
            </div>
            <div>{{ __('school_bulk.no_master_locations') }}</div>
        </section>
    @endforelse
</div>
</body>
</html>


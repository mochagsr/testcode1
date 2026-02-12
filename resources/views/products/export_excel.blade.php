<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            text-align: left;
            font-weight: 700;
        }
        .col-no { width: 56px; }
        .col-code { width: 180px; }
        .col-name { width: 520px; }
        .col-stock { width: 120px; text-align: right; }
        .title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            font-family: Arial, sans-serif;
        }
        .meta {
            margin-bottom: 12px;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="title">{{ __('ui.products_title') }}</div>
<div class="meta">{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>

<table>
    <thead>
    <tr>
        <th class="col-no">No</th>
        <th class="col-code">{{ __('ui.code') }}</th>
        <th class="col-name">{{ __('ui.name') }}</th>
        <th class="col-stock">{{ __('ui.stock') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($products as $index => $product)
        <tr>
            <td class="col-no">{{ $index + 1 }}</td>
            <td class="col-code">{{ $product->code ?: '-' }}</td>
            <td class="col-name">{{ $product->name }}</td>
            <td class="col-stock">{{ number_format((int) round((float) $product->stock), 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4">{{ __('ui.no_products') }}</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>

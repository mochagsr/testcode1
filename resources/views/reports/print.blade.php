<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .container { max-width: 1100px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; margin-bottom: 14px; }
        .title { font-size: 24px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f1f1f1; }
        .no-print { margin-bottom: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="container">
    <div class="no-print">
        <button onclick="window.print()">{{ __('report.print_save_pdf') }}</button>
    </div>
    <div class="head">
        <div class="title">{{ $title }}</div>
        <div>{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>
    </div>
    @if(!empty($filters))
        <table style="margin-bottom: 12px; width: 420px;">
            <tbody>
            @foreach($filters as $item)
                <tr>
                    <th style="width: 40%; background: #f1f1f1;">{{ $item['label'] }}</th>
                    <td>{{ $item['value'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if(!empty($summary))
        <table style="margin-bottom: 12px; width: 420px;">
            <tbody>
            @foreach($summary as $item)
                <tr>
                    <th style="width: 60%; background: #f1f1f1;">{{ $item['label'] }}</th>
                    <td>
                        @if(($item['type'] ?? 'number') === 'currency')
                            Rp {{ number_format((float) ($item['value'] ?? 0), 2) }}
                        @else
                            {{ number_format((float) ($item['value'] ?? 0), 0) }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <table>
        <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach($row as $value)
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}">{{ __('report.no_data') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>

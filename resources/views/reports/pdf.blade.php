<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .head { margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #efefef; }
    </style>
</head>
<body>
    <div class="head">
        <div class="title">{{ $title }}</div>
        <div>{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>
    </div>
    @if(!empty($filters))
        <table style="margin-bottom: 10px; width: 340px;">
            <tbody>
            @foreach($filters as $item)
                <tr>
                    <th style="width: 40%;">{{ $item['label'] }}</th>
                    <td>{{ $item['value'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if(!empty($summary))
        <table style="margin-bottom: 10px; width: 340px;">
            <tbody>
            @foreach($summary as $item)
                <tr>
                    <th style="width: 60%;">{{ $item['label'] }}</th>
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
</body>
</html>

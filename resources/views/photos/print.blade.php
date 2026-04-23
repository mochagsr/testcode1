<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'Laravel') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #111;
            background: #f5f5f5;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }
        .btn {
            display: inline-block;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .sheet {
            width: 190mm;
            min-height: 267mm;
            margin: 16px auto;
            padding: 10mm;
            background: #fff;
            box-sizing: border-box;
            border: 1px solid #ddd;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 18px;
            text-align: center;
            text-transform: uppercase;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10mm;
            font-size: 12px;
        }
        .meta th,
        .meta td {
            padding: 4px 6px;
            border: 1px solid #999;
            text-align: left;
            vertical-align: top;
        }
        .meta th {
            width: 42mm;
            background: #f0f0f0;
        }
        .photo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 190mm;
            border: 1px solid #999;
            padding: 6mm;
            box-sizing: border-box;
        }
        .photo-wrap img {
            max-width: 100%;
            max-height: 180mm;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        @media print {
            body {
                background: #fff;
            }
            .toolbar {
                display: none;
            }
            .sheet {
                margin: 0;
                border: 0;
                width: auto;
                min-height: auto;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn" onclick="window.print()">Print</button>
    </div>
    <main class="sheet">
        <h1>{{ $documentLabel }}</h1>
        <table class="meta">
            <tr>
                <th>{{ $subjectLabel }}</th>
                <td>{{ $subjectName }}</td>
            </tr>
            <tr>
                <th>{{ $referenceLabel }}</th>
                <td>{{ $referenceValue }}</td>
            </tr>
            <tr>
                <th>Tanggal Print</th>
                <td>{{ now('Asia/Jakarta')->format('d-m-Y H:i') }}</td>
            </tr>
        </table>
        <div class="photo-wrap">
            <img src="{{ $imageUrl }}" alt="{{ $documentLabel }}">
        </div>
    </main>
</body>
</html>

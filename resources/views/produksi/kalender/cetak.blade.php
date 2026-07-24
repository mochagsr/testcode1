<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK {{ $spk->no_spk }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { color: #000 !important; }
        body { font-family: "Times New Roman", serif; font-size: 12px; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: top; }
        .no-border, .no-border td, .no-border th { border: 0; padding: 2px 4px; }
        .r { text-align: right; }
        .c { text-align: center; }
        .sec { font-weight: bold; text-transform: uppercase; font-size: 11px; margin: 10px 0 2px; border-bottom: 1px solid #000; }
        .ttd { height: 60px; vertical-align: bottom; }
        .badge { border: 1px solid #000; padding: 1px 6px; font-size: 11px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body onload="window.print()">
@php
    $tahapLabels = ['ctcp' => 'Pembuatan Plat / CTCP', 'web' => 'Cetak Isi (Web)', 'sheet' => 'Cetak Cover (Sheet)', 'finishing' => 'Finishing'];
    $fmt = fn ($n) => $n === null ? '—' : number_format((int) $n, 0, ',', '.');
@endphp
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h1>SURAT PERINTAH KERJA</h1>
            <div style="font-size:13px; font-weight:bold;">No. {{ $spk->no_spk }}</div>
        </div>
        <div class="c">
            <div style="font-weight:bold;">{{ config('app.name') }}</div>
            <span class="badge">{{ strtoupper($spk->computedStatus()) }}</span>
        </div>
    </div>

    <table class="no-border">
        <tr><td style="width:110px;">Konsumen</td><td>: {{ $spk->konsumen }}</td><td style="width:110px;">Tgl Order</td><td>: {{ $spk->tanggal_order?->format('d-m-Y') }}</td></tr>
        <tr><td>Alamat</td><td>: {{ $spk->alamat ?: '-' }}</td><td>Deadline</td><td>: {{ $spk->deadline_kirim?->format('d-m-Y') }}</td></tr>
        <tr><td>Jenis Order</td><td>: {{ $spk->jenis_order ?: '-' }}</td><td>Ukuran Jadi</td><td>: {{ $spk->ukuran_jadi ?: '-' }}</td></tr>
        <tr><td>Finishing</td><td>: {{ $spk->finishing ?: '-' }}</td><td>Packing</td><td>: {{ $spk->packing ?: '-' }}</td></tr>
        @if($spk->catatan)<tr><td>Catatan</td><td colspan="3">: {{ $spk->catatan }}</td></tr>@endif
    </table>

    @if($spk->pakai_web || $spk->pakai_sheet)
        <div class="sec">Bahan Baku</div>
        <table>
            <tr><th style="width:60px;">Bagian</th><th>Kertas</th><th>Warna</th><th>Mesin</th><th>Waste</th></tr>
            @if($spk->pakai_web)
                <tr><td>Web</td><td>{{ $spk->web_kertas ?: '-' }}</td><td>{{ $spk->web_warna ?: '-' }}</td><td>{{ $spk->web_mesin ?: '-' }}</td><td>{{ $spk->web_waste ?: '-' }}</td></tr>
            @endif
            @if($spk->pakai_sheet)
                <tr><td>Sheet</td><td>{{ $spk->sheet_kertas ?: '-' }}</td><td>{{ $spk->sheet_warna ?: '-' }}</td><td>{{ $spk->sheet_mesin ?: ($spk->mesin_cover ?: '-') }}</td><td>{{ $spk->sheet_waste ?: '-' }}</td></tr>
            @endif
        </table>
    @endif

    <div class="sec">Rincian Barang</div>
    <table>
        <tr>
            <th>Nama</th><th class="c" style="width:40px;">Hal</th><th class="c" style="width:40px;">Kls</th>
            @if($spk->pakai_web)<th class="r" style="width:80px;">Cetak Web</th>@endif
            @if($spk->pakai_sheet)<th class="r" style="width:80px;">Cetak Sheet</th>@endif
        </tr>
        @foreach($spk->items as $item)
            <tr>
                <td>{{ $item->nama_barang }}</td>
                <td class="c">{{ $item->halaman ?: '-' }}</td>
                <td class="c">{{ $item->kelas ?: '-' }}</td>
                @if($spk->pakai_web)<td class="r">{{ $fmt($item->cetak_isi) }}</td>@endif
                @if($spk->pakai_sheet)<td class="r">{{ $fmt($item->cetak_sheet) }}</td>@endif
            </tr>
        @endforeach
    </table>

    <div class="sec">Tahapan Produksi</div>
    <table>
        <tr><th>Tahap</th><th>PIC</th><th class="r" style="width:80px;">Rencana</th><th class="r" style="width:80px;">Realisasi</th><th class="c" style="width:70px;">Tgl Selesai</th></tr>
        @foreach($spk->stages as $stage)
            <tr>
                <td>{{ $tahapLabels[$stage->tahap] ?? $stage->tahap }}</td>
                <td>{{ $stage->pic ?: '-' }}</td>
                <td class="r">{{ $fmt($stage->qty_rencana) }}</td>
                <td class="r">{{ $fmt($stage->qty_realisasi) }}</td>
                <td class="c">{{ $stage->tanggal_realisasi?->format('d-m-Y') ?: '________' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="sec">Penanggung Jawab</div>
    <table>
        <tr>
            @forelse($spk->penanggungJawabs as $pj)
                <td class="c" style="width:{{ max(1, floor(100 / max(1, $spk->penanggungJawabs->count()))) }}%;">
                    {{ $pj->jabatan }}<div class="ttd"></div>( {{ $pj->nama ?: '________' }} )<br>Tgl: __________
                </td>
            @empty
                <td class="c">PPIC<div class="ttd"></div>( ________ )</td>
            @endforelse
        </tr>
    </table>

    <div class="noprint" style="margin-top:10px;">
        <button onclick="window.print()">Cetak</button>
    </div>
</body>
</html>

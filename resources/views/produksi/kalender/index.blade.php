@extends('layouts.app')

@section('title', 'Kalender Produksi - '.config('app.name', 'Laravel'))

@section('content')
@include('produksi.kalender.partials.styles')
<div class="pk-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h1 class="page-title" style="margin:0;">Kalender Produksi</h1>
        <div style="display:flex; gap:8px;">
            <a class="btn secondary" href="{{ route('produksi.riwayat') }}">Riwayat SPK</a>
            @if($canExport)
                <a class="btn secondary" id="pk-export-btn" href="{{ route('produksi.kalender.export') }}">Excel</a>
            @endif
            @if($canManage)
                <a class="btn" href="{{ route('produksi.spk.create') }}">+ SPK Baru</a>
            @endif
        </div>
    </div>

    {{-- Persistent toolbar (tabs + filters stay while the calendar region swaps) --}}
    <div class="pk-toolbar" style="margin-top:12px;">
        <div class="pk-tabs" id="pk-tabs">
            <button type="button" data-tab="bulan" class="active">Bulan</button>
            <button type="button" data-tab="timeline">Jadwal Mesin</button>
            <button type="button" data-tab="daftar">Daftar</button>
        </div>
        <div style="margin-left:auto; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select id="pk-filter-konsumen">
                <option value="">Semua Konsumen</option>
                @foreach($konsumenOptions as $k)
                    <option value="{{ $k }}">{{ $k }}</option>
                @endforeach
            </select>
            <div class="pk-chips" id="pk-filter-status">
                <span class="pk-chip active" data-status="all">Semua</span>
                <span class="pk-chip" data-status="antre">Antre</span>
                <span class="pk-chip" data-status="proses">Proses</span>
                <span class="pk-chip" data-status="selesai">Selesai</span>
                <span class="pk-chip" data-status="telat">Telat</span>
            </div>
        </div>
    </div>

    {{-- Swappable calendar region (AJAX month navigation) --}}
    <div id="pk-calendar-region">
        @include('produksi.kalender.partials.region')
    </div>
</div>

{{-- Detail popup --}}
<div class="pk-overlay" id="pk-detail-overlay">
    <div class="pk-wrap pk-modal-box narrow"><div class="pk-modal-scroll" id="pk-detail-body"></div></div>
</div>

@if($canManage)
    @include('produksi.kalender.partials.modal')
@endif

<script>
    window.PK = {
        lookupUrl: @json($canManage ? route('produksi.kalender.lookup') : null),
        showUrlTpl: @json(url('/produksi/spk/__ID__')),
        updateUrlTpl: @json(url('/produksi/spk/__ID__')),
        storeUrl: @json($canManage ? route('produksi.spk.store') : ''),
        exportUrl: @json($canExport ? route('produksi.kalender.export') : null),
        openId: @json($openId ?? null),
        data: {},
    };
</script>
@include('produksi.kalender.partials.script')
@endsection

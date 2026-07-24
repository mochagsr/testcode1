<div class="pk-monthnav" style="margin-bottom:12px;">
    <a href="{{ route('produksi.kalender.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" data-pk-nav>‹</a>
    <span class="lbl">{{ $monthLabel }}</span>
    <a href="{{ route('produksi.kalender.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" data-pk-nav>›</a>
</div>

<div class="pk-kpis">
    <div class="pk-kpi total"><div class="n" id="pk-kpi-total">{{ $kpi['total'] }}</div><div class="l">Total SPK</div></div>
    <div class="pk-kpi antre"><div class="n" id="pk-kpi-antre">{{ $kpi['antre'] }}</div><div class="l">Antre</div></div>
    <div class="pk-kpi proses"><div class="n" id="pk-kpi-proses">{{ $kpi['proses'] }}</div><div class="l">Proses</div></div>
    <div class="pk-kpi selesai"><div class="n" id="pk-kpi-selesai">{{ $kpi['selesai'] }}</div><div class="l">Selesai</div></div>
    <div class="pk-kpi telat"><div class="n" id="pk-kpi-telat">{{ $kpi['telat'] }}</div><div class="l">Telat</div></div>
</div>

<div data-panel="bulan">@include('produksi.kalender.partials.month')</div>
<div data-panel="timeline" class="pk-hidden">@include('produksi.kalender.partials.timeline')</div>
<div data-panel="daftar" class="pk-hidden">@include('produksi.kalender.partials.list')</div>

<script type="application/json" id="pk-data-json">@json($spkData)</script>

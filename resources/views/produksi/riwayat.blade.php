@extends('layouts.app')

@section('title', 'Riwayat SPK - '.config('app.name', 'Laravel'))

@section('content')
@include('produksi.kalender.partials.styles')
<div class="pk-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
        <h1 class="page-title" style="margin:0;">Riwayat SPK</h1>
        <a class="btn secondary" href="{{ route('produksi.kalender.index') }}">← Kalender</a>
    </div>

    <form method="get" class="pk-panel" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px;">
        <div><label>Dari (deadline)</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>Sampai</label><input type="date" name="to" value="{{ $to }}"></div>
        <div>
            <label>Konsumen</label>
            <select name="konsumen">
                <option value="">Semua Konsumen</option>
                @foreach($konsumenOptions as $k)
                    <option value="{{ $k }}" @selected($konsumen === $k)>{{ $k }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="">Semua Status</option>
                @foreach(['selesai' => 'Selesai', 'proses' => 'Proses', 'antre' => 'Antre', 'telat' => 'Telat'] as $val => $lbl)
                    <option value="{{ $val }}" @selected($status === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn">Terapkan</button>
    </form>

    <div class="pk-panel">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>No. SPK</th><th>Konsumen</th><th>Jenis</th>
                    <th>Tgl Order</th><th>Deadline</th><th>Status</th><th style="width:150px;">Progress</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($spks as $spk)
                    @php $st = $spk->computedStatus(); $pct = (int) round($spk->progress() * 100); @endphp
                    <tr>
                        <td class="pk-mono" style="font-weight:600;">{{ $spk->no_spk }}</td>
                        <td>{{ $spk->konsumen }}<div class="pk-muted" style="font-size:11px;">{{ $spk->alamat }}</div></td>
                        <td>{{ $spk->jenis_order }}</td>
                        <td class="pk-mono">{{ $spk->tanggal_order?->format('d M Y') }}</td>
                        <td class="pk-mono">{{ $spk->deadline_kirim?->format('d M Y') }}</td>
                        <td><span class="pk-status {{ $st }}">{{ ucfirst($st) }}</span></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="pk-prog"><span style="width:{{ $pct }}%;"></span></div>
                                <span class="pk-mono pk-muted" style="font-size:11px;">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td><button type="button" class="btn secondary pk-spk-el" data-spk-id="{{ $spk->id }}" style="padding:4px 10px;">Lihat</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="pk-muted" style="text-align:center; padding:24px;">Tidak ada SPK pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px;">{{ $spks->links() }}</div>
    </div>
</div>

{{-- Detail popup --}}
<div class="pk-overlay" id="pk-detail-overlay">
    <div class="pk-wrap pk-modal-box narrow"><div class="pk-modal-scroll" id="pk-detail-body"></div></div>
</div>
<script>
(function () {
    const showTpl = @json(url('/produksi/spk/__ID__'));
    const overlay = document.getElementById('pk-detail-overlay');
    const body = document.getElementById('pk-detail-body');
    function close() { overlay.classList.remove('open'); }
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('click', (e) => {
        const el = e.target.closest('.pk-spk-el');
        if (el) {
            fetch(showTpl.replace('__ID__', el.getAttribute('data-spk-id')), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.text()).then((html) => { body.innerHTML = html; overlay.classList.add('open'); });
            return;
        }
        if (e.target.closest('.pk-drawer-close')) close();
        if (e.target.closest('#pk-toggle-realisasi')) document.getElementById('pk-realisasi-form-wrap')?.classList.toggle('pk-hidden');
    });
})();
</script>
@endsection

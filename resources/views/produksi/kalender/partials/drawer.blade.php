@php
    $status = $spk->computedStatus();
    $statusLabels = ['antre' => 'Antre', 'proses' => 'Proses', 'selesai' => 'Selesai', 'telat' => 'Telat'];
    $tahapLabels = ['ctcp' => 'Pembuatan Plat / CTCP', 'web' => 'Cetak Isi (Web)', 'sheet' => 'Cetak Cover (Sheet)', 'finishing' => 'Finishing'];
    $fmt = fn ($n) => $n === null ? '—' : number_format((int) $n, 0, ',', '.');
@endphp
<div class="pk-wrap" style="font-size:13px;">
    <div style="background:#2457c5; color:#fff; padding:18px 20px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; position:sticky; top:0; z-index:1;">
        <div>
            <div class="pk-mono" style="font-size:16px; font-weight:700;">{{ $spk->no_spk }}</div>
            <div style="opacity:.9; margin-top:2px;">{{ $spk->konsumen }} @if($spk->alamat)<span style="opacity:.7;">· {{ $spk->alamat }}</span>@endif</div>
        </div>
        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
            <span class="pk-status {{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
            <button type="button" class="pk-drawer-close" style="background:transparent; border:1px solid rgba(255,255,255,.5); color:#fff; border-radius:6px; padding:4px 10px; cursor:pointer;">Tutup</button>
        </div>
    </div>

    <div style="padding:16px 20px;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; margin-bottom:16px;">
            <div><div class="muted" style="font-size:11px;">Jenis Order</div><div>{{ $spk->jenis_order ?: '—' }}</div></div>
            <div><div class="muted" style="font-size:11px;">Ukuran Jadi</div><div>{{ $spk->ukuran_jadi ?: '—' }}</div></div>
            <div><div class="muted" style="font-size:11px;">Tgl Order</div><div class="pk-mono">{{ $spk->tanggal_order?->format('d M Y') }}</div></div>
            <div><div class="muted" style="font-size:11px;">Deadline</div><div class="pk-mono">{{ $spk->deadline_kirim?->format('d M Y') }}</div></div>
            <div><div class="muted" style="font-size:11px;">Finishing</div><div>{{ $spk->finishing ?: '—' }}</div></div>
            <div><div class="muted" style="font-size:11px;">Packing</div><div>{{ $spk->packing ?: '—' }}</div></div>
            @if($spk->catatan)
                <div style="grid-column:1/3;"><div class="muted" style="font-size:11px;">Catatan</div><div>{{ $spk->catatan }}</div></div>
            @endif
        </div>

        {{-- Bahan Baku --}}
        @if($spk->pakai_web || $spk->pakai_sheet)
            <h3 style="font-size:12px; text-transform:uppercase; color:var(--muted); letter-spacing:.4px; margin:0 0 8px;">Bahan Baku</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:10px; margin-bottom:16px;">
                @if($spk->pakai_web)
                    <div class="pk-panel"><b>Web (Cetak Isi)</b>
                        <table class="pk-table" style="margin-top:6px;">
                            <tr><td>Kertas</td><td>{{ $spk->web_kertas ?: '—' }}</td></tr>
                            <tr><td>Warna</td><td>{{ $spk->web_warna ?: '—' }}</td></tr>
                            <tr><td>Mesin</td><td>{{ $spk->web_mesin ?: '—' }}</td></tr>
                            <tr><td>Waste</td><td>{{ $spk->web_waste ?: '—' }}</td></tr>
                        </table>
                    </div>
                @endif
                @if($spk->pakai_sheet)
                    <div class="pk-panel"><b>Sheet (Cetak Cover)</b>
                        <table class="pk-table" style="margin-top:6px;">
                            <tr><td>Kertas</td><td>{{ $spk->sheet_kertas ?: '—' }}</td></tr>
                            <tr><td>Warna</td><td>{{ $spk->sheet_warna ?: '—' }}</td></tr>
                            <tr><td>Mesin</td><td>{{ $spk->sheet_mesin ?: ($spk->mesin_cover ?: '—') }}</td></tr>
                            <tr><td>Waste</td><td>{{ $spk->sheet_waste ?: '—' }}</td></tr>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- Tahapan Produksi --}}
        <h3 style="font-size:12px; text-transform:uppercase; color:var(--muted); letter-spacing:.4px; margin:0 0 8px;">Tahapan Produksi</h3>
        <table class="pk-table" style="margin-bottom:16px;">
            <thead><tr><th>Tahap</th><th>PIC</th><th style="text-align:right;">Rencana</th><th style="text-align:right;">Realisasi</th><th style="text-align:right;">Selisih</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($spk->stages as $stage)
                    @php $dev = $stage->deviasi(); $sstatus = $stage->computedStatus(); @endphp
                    <tr>
                        <td>{{ $tahapLabels[$stage->tahap] ?? $stage->tahap }}</td>
                        <td>{{ $stage->pic ?: '—' }}</td>
                        <td class="pk-mono" style="text-align:right;">{{ $fmt($stage->qty_rencana) }}</td>
                        <td class="pk-mono" style="text-align:right;">{{ $fmt($stage->qty_realisasi) }}</td>
                        <td class="pk-mono" style="text-align:right; color: {{ $dev === null ? 'var(--muted)' : ($dev < 0 ? '#b3261e' : '#1f7a45') }};">
                            {{ $dev === null ? '—' : ($dev > 0 ? '+' : '').number_format($dev, 0, ',', '.') }}
                        </td>
                        <td><span class="pk-status {{ $sstatus }}">{{ $statusLabels[$sstatus] ?? ucfirst($sstatus) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Rincian Barang --}}
        <h3 style="font-size:12px; text-transform:uppercase; color:var(--muted); letter-spacing:.4px; margin:0 0 8px;">Rincian Barang</h3>
        @if($spk->jenis_cetak === 'sebagian' && $spk->revisi_bagian)
            <p class="muted" style="font-size:11px; margin:0 0 6px;">Bagian dicetak ulang: <b>{{ $spk->revisi_bagian }}</b></p>
        @endif
        <table class="pk-table" style="margin-bottom:16px;">
            <thead>
                <tr>
                    <th>Nama</th><th>Hal</th><th>Kls</th>
                    <th>Barang Umum</th>
                    @if($spk->pakai_web)<th style="text-align:right;">Cetak Web</th>@endif
                    @if($spk->pakai_sheet)<th style="text-align:right;">Cetak Sheet</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($spk->items as $item)
                    <tr>
                        <td>{{ $item->nama_barang }}</td>
                        <td>{{ $item->halaman ?: '—' }}</td>
                        <td>{{ $item->kelas ?: '—' }}</td>
                        <td>{{ $item->product?->name ?: '—' }}</td>
                        @if($spk->pakai_web)<td class="pk-mono" style="text-align:right;">{{ $fmt($item->cetak_isi) }}</td>@endif
                        @if($spk->pakai_sheet)<td class="pk-mono" style="text-align:right;">{{ $fmt($item->cetak_sheet) }}</td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Penanggung Jawab --}}
        @if($spk->penanggungJawabs->isNotEmpty())
            <h3 style="font-size:12px; text-transform:uppercase; color:var(--muted); letter-spacing:.4px; margin:0 0 8px;">Penanggung Jawab</h3>
            <table class="pk-table" style="margin-bottom:16px;">
                @foreach($spk->penanggungJawabs as $pj)
                    <tr><td style="color:var(--muted);">{{ $pj->jabatan }}</td><td>{{ $pj->nama ?: '—' }}</td></tr>
                @endforeach
            </table>
        @endif

        {{-- Realisasi form (operator) --}}
        @if($canRealisasi)
            <div id="pk-realisasi-form-wrap" class="pk-hidden">
                <h3 style="font-size:12px; text-transform:uppercase; color:var(--muted); letter-spacing:.4px; margin:16px 0 8px;">Input Realisasi</h3>
                <form method="post" action="{{ route('produksi.spk.realisasi', $spk) }}">
                    @csrf
                    @foreach($spk->stages as $i => $stage)
                        <div class="pk-panel" style="margin-bottom:8px;">
                            <div style="font-weight:600; margin-bottom:6px;">{{ $tahapLabels[$stage->tahap] ?? $stage->tahap }}</div>
                            <input type="hidden" name="stages[{{ $i }}][id]" value="{{ $stage->id }}">
                            <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:end;">
                                <div><label style="font-size:11px;">Jumlah realisasi</label><input type="number" min="0" name="stages[{{ $i }}][qty_realisasi]" value="{{ $stage->qty_realisasi }}"></div>
                                <div><label style="font-size:11px;">Tanggal selesai</label><input type="date" name="stages[{{ $i }}][tanggal_realisasi]" value="{{ $stage->tanggal_realisasi?->format('Y-m-d') }}"></div>
                                <label style="display:flex; align-items:center; gap:6px; font-size:12px; white-space:nowrap;"><input type="checkbox" name="stages[{{ $i }}][selesai]" value="1" @checked($stage->selesai)> Selesai</label>
                            </div>
                        </div>
                    @endforeach

                    <div class="pk-panel" style="margin-bottom:8px;">
                        <div style="font-weight:600; margin-bottom:6px;">Jumlah Jadi per Item (masuk stok barang umum saat Finishing selesai)</div>
                        @foreach($spk->items as $i => $item)
                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:5px 0; border-bottom:1px solid var(--border);">
                                <div>{{ $item->nama_barang }} <span class="muted" style="font-size:11px;">→ {{ $item->product?->name ?: 'belum ditautkan' }}</span></div>
                                <input type="number" min="0" style="width:120px;" name="items[{{ $i }}][jumlah_jadi_realisasi]" value="{{ $item->jumlah_jadi_realisasi }}" @disabled($item->product_id === null)>
                            </div>
                        @endforeach
                        <p class="muted" style="font-size:11px; margin:6px 0 0;">Item tanpa produk umum tidak menambah stok. Tautkan produk lewat Edit SPK.</p>
                    </div>

                    <button type="submit" class="btn">Simpan Realisasi</button>
                </form>
            </div>
        @endif

        <div style="display:flex; gap:8px; margin-top:16px; flex-wrap:wrap;">
            @if($canManage)
                <button type="button" class="btn secondary pk-edit-spk" data-spk-id="{{ $spk->id }}">Edit</button>
            @endif
            <a class="btn secondary" href="{{ route('produksi.spk.cetak', $spk) }}" target="_blank">Cetak / PDF</a>
            @if($canRealisasi)
                <button type="button" class="btn" id="pk-toggle-realisasi">Input Realisasi</button>
            @endif
        </div>
    </div>
</div>

<div id="pk-modal-overlay" class="pk-overlay">
    <div class="pk-wrap pk-modal-box">
        <div style="background:#2457c5; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
            <b id="pk-modal-title">Buat SPK Baru</b>
            <button type="button" id="pk-modal-close" class="btn secondary" style="padding:4px 10px;">Batal</button>
        </div>
        <form id="pk-spk-form" method="post" action="{{ route('produksi.spk.store') }}" style="padding:16px 20px;">
            @csrf
            <input type="hidden" name="_method" id="pk-form-method" value="POST">

            <p class="muted" style="font-size:12px; margin:0 0 12px;">No. SPK dibuat otomatis (urut/SPK/bulan-romawi/tahun, reset tiap semester).</p>

            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                <div><label>Konsumen *</label><input type="text" name="konsumen" required></div>
                <div><label>Alamat</label><input type="text" name="alamat"></div>
                <div><label>Jenis Order</label><input type="text" name="jenis_order"></div>
                <div><label>Tgl Order *</label><input type="date" name="tanggal_order" required></div>
                <div><label>Deadline Kirim *</label><input type="date" name="deadline_kirim" required></div>
                <div><label>Mesin Cover</label><input type="text" name="mesin_cover" list="pk-mesin-cover"></div>
                <div><label>Finishing</label><input type="text" name="finishing"></div>
                <div><label>Packing</label><input type="text" name="packing"></div>
                <div><label>Ukuran Jadi</label><input type="text" name="ukuran_jadi"></div>
                <div style="grid-column:1/4;"><label>Catatan</label><textarea name="catatan" rows="2"></textarea></div>
            </div>
            <datalist id="pk-mesin-cover"><option value="Lithrone"></option><option value="Komori 66"></option></datalist>

            {{-- Bahan Baku --}}
            <h3 style="font-size:12px; text-transform:uppercase; color:#7a8798; margin:16px 0 8px;">Bahan Baku (minimal satu)</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="pk-panel" data-bahan="web">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600;"><input type="checkbox" name="pakai_web" value="1" class="pk-toggle-web" checked> Web (Cetak Isi)</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:8px;">
                        <input type="text" name="web_kertas" placeholder="Kertas">
                        <input type="text" name="web_warna" placeholder="Warna">
                        <input type="text" name="web_mesin" placeholder="Mesin">
                        <input type="text" name="web_waste" placeholder="Waste">
                    </div>
                </div>
                <div class="pk-panel" data-bahan="sheet">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600;"><input type="checkbox" name="pakai_sheet" value="1" class="pk-toggle-sheet" checked> Sheet (Cetak Cover)</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:8px;">
                        <input type="text" name="sheet_kertas" placeholder="Kertas">
                        <input type="text" name="sheet_warna" placeholder="Warna">
                        <input type="text" name="sheet_mesin" placeholder="Mesin">
                        <input type="text" name="sheet_waste" placeholder="Waste">
                    </div>
                </div>
            </div>

            {{-- Jenis Cetak --}}
            <h3 style="font-size:12px; text-transform:uppercase; color:#7a8798; margin:16px 0 8px;">Jenis Cetak</h3>
            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <label class="pk-chip"><input type="radio" name="jenis_cetak" value="penuh" checked class="pk-jenis"> Penuh</label>
                <label class="pk-chip"><input type="radio" name="jenis_cetak" value="sebagian" class="pk-jenis"> Sebagian</label>
            </div>
            <div id="pk-sebagian-box" class="pk-hidden">
                <div><label>Bagian yang dicetak ulang</label><input type="text" name="revisi_bagian" placeholder="mis. Kata pengantar, Cover"></div>
                <div style="margin-top:8px;">
                    <label>Versi File</label>
                    <div id="pk-versi-list" style="display:flex; flex-direction:column; gap:6px;"></div>
                    <button type="button" id="pk-add-versi" class="btn secondary" style="margin-top:6px; padding:4px 10px;">+ Versi</button>
                </div>
            </div>

            {{-- Rincian Barang --}}
            <h3 style="font-size:12px; text-transform:uppercase; color:#7a8798; margin:16px 0 8px;">Rincian Barang</h3>
            <div style="overflow-x:auto;">
                <table class="pk-table" id="pk-items-table">
                    <thead>
                        <tr>
                            <th style="min-width:150px;">Nama</th>
                            <th style="min-width:150px;">Barang Umum (stok)</th>
                            <th>Hal</th><th>Kls</th>
                            <th class="pk-col-web" style="min-width:90px;">Cetak Web</th>
                            <th class="pk-col-sheet" style="min-width:90px;">Cetak Sheet</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="pk-items-body"></tbody>
                </table>
            </div>
            <button type="button" id="pk-add-item" class="btn secondary" style="margin-top:6px; padding:4px 10px;">+ Baris Barang</button>

            {{-- Penanggung Jawab --}}
            <h3 style="font-size:12px; text-transform:uppercase; color:#7a8798; margin:16px 0 8px;">Penanggung Jawab</h3>
            <div id="pk-pj-list" style="display:flex; flex-direction:column; gap:6px;"></div>
            <button type="button" id="pk-add-pj" class="btn secondary" style="margin-top:6px; padding:4px 10px;">+ Penanggung Jawab</button>

            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn" id="pk-save-btn">Simpan SPK</button>
                <button type="button" id="pk-modal-cancel" class="btn secondary">Batal</button>
            </div>
        </form>
    </div>
</div>

{{-- Templates cloned by JS --}}
<template id="pk-item-tpl">
    <tr class="pk-item-row">
        <td><input type="text" class="pk-item-nama" placeholder="Nama barang"></td>
        <td style="position:relative;">
            <input type="text" class="pk-item-prod-search" placeholder="cari produk umum..." autocomplete="off">
            <input type="hidden" class="pk-item-prod-id">
            <div class="pk-prod-results" style="display:none; position:absolute; z-index:20; left:0; right:0; background:#fff; border:1px solid #e3e8ee; border-radius:6px; max-height:180px; overflow:auto;"></div>
        </td>
        <td><input type="text" class="pk-item-hal" style="width:60px;"></td>
        <td><input type="text" class="pk-item-kls" style="width:60px;"></td>
        <td class="pk-col-web"><input type="number" min="0" class="pk-item-web" style="width:90px;"></td>
        <td class="pk-col-sheet"><input type="number" min="0" class="pk-item-sheet" style="width:90px;"></td>
        <td><button type="button" class="btn danger-btn pk-del-item" style="padding:3px 8px;">×</button></td>
    </tr>
</template>
<template id="pk-versi-tpl">
    <div style="display:flex; gap:6px; align-items:center;" class="pk-versi-row">
        <input type="text" class="pk-versi-nama" placeholder="Nama versi (mis. Patrang)">
        <button type="button" class="btn danger-btn pk-del-versi" style="padding:3px 8px;">×</button>
    </div>
</template>
<template id="pk-pj-tpl">
    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:6px;" class="pk-pj-row">
        <input type="text" class="pk-pj-jabatan" placeholder="Jabatan (mis. PPIC)">
        <input type="text" class="pk-pj-nama" placeholder="Nama">
        <button type="button" class="btn danger-btn pk-del-pj" style="padding:3px 8px;">×</button>
    </div>
</template>

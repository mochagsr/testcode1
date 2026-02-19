@extends('layouts.app')

@section('title', 'Wizard Koreksi Transaksi - PgPOS ERP')

@section('content')
    <h1 class="page-title">Wizard Koreksi Transaksi</h1>

    <div class="card">
        <form method="get" class="row" style="margin-bottom: 10px;">
            <div class="col-6">
                <label>Tipe Dokumen</label>
                <select name="type" onchange="this.form.submit()">
                    @foreach($types as $typeOption)
                        <option value="{{ $typeOption }}" @selected($type === $typeOption)>{{ str_replace('_', ' ', strtoupper($typeOption)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <label>ID Dokumen</label>
                <input type="number" name="id" value="{{ $subjectId }}" min="1" placeholder="Masukkan ID dokumen lalu Enter">
            </div>
        </form>
    </div>

    <div class="card">
        <form method="post" action="{{ route('transaction-corrections.store') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">

            <div class="row">
                <div class="col-12">
                    <label>Dokumen</label>
                    <input type="text" value="{{ $subjectLabel }}" disabled>
                </div>
                <div class="col-12">
                    <label>Alasan Koreksi <span class="label-required">*</span></label>
                    <textarea name="reason" rows="3" required>{{ old('reason') }}</textarea>
                </div>
                <div class="col-12">
                    <label>Detail Perubahan Diminta <span class="label-required">*</span></label>
                    <textarea name="requested_changes" rows="8" required placeholder="Contoh: Ubah qty item A dari 10 menjadi 12, tambah item B qty 2">{{ old('requested_changes') }}</textarea>
                </div>
                @if($type === 'sales_invoice')
                    <div class="col-12">
                        <label>Patch Koreksi Invoice (JSON untuk auto-eksekusi)</label>
                        <textarea id="requested_patch_json" name="requested_patch_json" rows="14" placeholder='{"invoice_date":"2026-02-20","items":[{"product_id":1,"quantity":5,"unit_price":12000,"discount":0}]}'>{{ old('requested_patch_json', $initialPatchJson) }}</textarea>
                        <p class="muted" style="margin: 6px 0 0 0;">Gunakan format JSON ini untuk auto-eksekusi setelah approval.</p>
                    </div>
                    <div class="col-12 flex">
                        <button type="button" id="preview-stock-impact" class="btn secondary">Preview Dampak Stok</button>
                        <span id="stock-impact-status" class="muted"></span>
                    </div>
                    <div class="col-12">
                        <div id="stock-impact-wrapper" style="display:none;">
                            <table id="stock-impact-table">
                                <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Qty Lama</th>
                                    <th>Qty Baru</th>
                                    <th>Delta Stok</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                @endif
                <div class="col-12 flex">
                    <button type="submit" class="btn">Kirim Permintaan Koreksi</button>
                    <a href="{{ url()->previous() }}" class="btn secondary">Kembali</a>
                </div>
            </div>
        </form>
    </div>

    @if($type === 'sales_invoice')
        <script>
            (function () {
                const btn = document.getElementById('preview-stock-impact');
                const textarea = document.getElementById('requested_patch_json');
                const statusEl = document.getElementById('stock-impact-status');
                const wrapper = document.getElementById('stock-impact-wrapper');
                const tbody = document.querySelector('#stock-impact-table tbody');
                if (!btn || !textarea || !statusEl || !wrapper || !tbody) {
                    return;
                }

                btn.addEventListener('click', async function () {
                    statusEl.textContent = 'Memproses...';
                    wrapper.style.display = 'none';
                    tbody.innerHTML = '';
                    try {
                        const response = await fetch('{{ route('transaction-corrections.preview-stock') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                type: '{{ $type }}',
                                subject_id: '{{ $subjectId }}',
                                requested_patch_json: textarea.value
                            })
                        });
                        const json = await response.json();
                        if (!response.ok || !json.ok) {
                            statusEl.textContent = 'Preview gagal. Cek format JSON.';
                            return;
                        }
                        (json.rows || []).forEach((row) => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = '<td>' + row.product_name + '</td>'
                                + '<td>' + row.old_qty + '</td>'
                                + '<td>' + row.new_qty + '</td>'
                                + '<td>' + row.delta_label + '</td>';
                            tbody.appendChild(tr);
                        });
                        wrapper.style.display = '';
                        statusEl.textContent = 'Preview selesai.';
                    } catch (e) {
                        statusEl.textContent = 'Preview gagal. Cek koneksi.';
                    }
                });
            })();
        </script>
    @endif
@endsection

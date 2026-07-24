<script>
(function () {
    const PK = window.PK || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const region = document.getElementById('pk-calendar-region');
    let currentTab = 'bulan';
    let activeStatus = 'all';

    function readData() {
        const node = document.getElementById('pk-data-json');
        try { PK.data = node ? JSON.parse(node.textContent) : {}; } catch (e) { PK.data = {}; }
    }

    function applyTab() {
        document.querySelectorAll('#pk-tabs button').forEach((b) => b.classList.toggle('active', b.getAttribute('data-tab') === currentTab));
        document.querySelectorAll('#pk-calendar-region [data-panel]').forEach((p) => {
            p.classList.toggle('pk-hidden', p.getAttribute('data-panel') !== currentTab);
        });
    }

    function applyFilters() {
        const konsumen = konsumenSel ? konsumenSel.value : '';
        document.querySelectorAll('.pk-spk-el').forEach((el) => {
            const okK = !konsumen || el.getAttribute('data-konsumen') === konsumen;
            const okS = activeStatus === 'all' || el.getAttribute('data-status') === activeStatus;
            el.classList.toggle('pk-hidden', !(okK && okS));
        });
        const counts = { total: 0, antre: 0, proses: 0, selesai: 0, telat: 0 };
        document.querySelectorAll('.pk-list-row').forEach((row) => {
            if (konsumen && row.getAttribute('data-konsumen') !== konsumen) return;
            const st = row.getAttribute('data-status');
            counts.total++; counts[st] = (counts[st] || 0) + 1;
        });
        ['total', 'antre', 'proses', 'selesai', 'telat'].forEach((k) => {
            const node = document.getElementById('pk-kpi-' + k);
            if (node) node.textContent = counts[k];
        });
        if (PK.exportUrl) {
            const u = new URL(PK.exportUrl, window.location.origin);
            konsumen ? u.searchParams.set('konsumen', konsumen) : u.searchParams.delete('konsumen');
            activeStatus !== 'all' ? u.searchParams.set('status', activeStatus) : u.searchParams.delete('status');
            document.getElementById('pk-export-btn')?.setAttribute('href', u.toString());
        }
    }

    // ---- Tabs ----
    document.querySelectorAll('#pk-tabs button').forEach((btn) => {
        btn.addEventListener('click', () => { currentTab = btn.getAttribute('data-tab'); applyTab(); });
    });

    // ---- Filters ----
    const konsumenSel = document.getElementById('pk-filter-konsumen');
    if (konsumenSel) konsumenSel.addEventListener('change', applyFilters);
    document.querySelectorAll('#pk-filter-status .pk-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('#pk-filter-status .pk-chip').forEach((c) => c.classList.remove('active'));
            chip.classList.add('active');
            activeStatus = chip.getAttribute('data-status');
            applyFilters();
        });
    });

    // ---- AJAX month navigation (swap region only) ----
    document.addEventListener('click', (e) => {
        const nav = e.target.closest('#pk-calendar-region a[data-pk-nav]');
        if (!nav) return;
        e.preventDefault();
        region.style.opacity = '0.5';
        fetch(nav.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.text())
            .then((html) => {
                region.innerHTML = html;
                region.style.opacity = '';
                readData(); applyTab(); applyFilters();
                window.history.replaceState({}, '', nav.href);
            })
            .catch(() => { region.style.opacity = ''; window.location = nav.href; });
    });

    // ---- Detail popup ----
    const detailOverlay = document.getElementById('pk-detail-overlay');
    const detailBody = document.getElementById('pk-detail-body');
    function openDetail(id) {
        if (!PK.showUrlTpl) return;
        fetch(PK.showUrlTpl.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.text())
            .then((html) => { detailBody.innerHTML = html; detailOverlay.classList.add('open'); });
    }
    function closeDetail() { detailOverlay?.classList.remove('open'); }
    if (detailOverlay) detailOverlay.addEventListener('click', (e) => { if (e.target === detailOverlay) closeDetail(); });

    document.addEventListener('click', (e) => {
        const el = e.target.closest('.pk-spk-el');
        if (el) { openDetail(el.getAttribute('data-spk-id')); return; }
        if (e.target.closest('.pk-drawer-close')) { closeDetail(); return; }
        if (e.target.closest('#pk-toggle-realisasi')) { document.getElementById('pk-realisasi-form-wrap')?.classList.toggle('pk-hidden'); return; }
        const editBtn = e.target.closest('.pk-edit-spk');
        if (editBtn) { openModalForEdit(editBtn.getAttribute('data-spk-id')); return; }
    });

    // ---- Modal ----
    const modalOverlay = document.getElementById('pk-modal-overlay');
    const form = document.getElementById('pk-spk-form');
    const itemsBody = document.getElementById('pk-items-body');
    const versiList = document.getElementById('pk-versi-list');
    const pjList = document.getElementById('pk-pj-list');
    function openModal() { modalOverlay?.classList.add('open'); }
    function closeModal() { modalOverlay?.classList.remove('open'); }
    ['pk-modal-close', 'pk-modal-cancel'].forEach((id) => document.getElementById(id)?.addEventListener('click', closeModal));

    function syncBahanColumns() {
        if (!form) return;
        const web = form.querySelector('.pk-toggle-web')?.checked;
        const sheet = form.querySelector('.pk-toggle-sheet')?.checked;
        form.querySelectorAll('.pk-col-web').forEach((el) => el.classList.toggle('pk-hidden', !web));
        form.querySelectorAll('.pk-col-sheet').forEach((el) => el.classList.toggle('pk-hidden', !sheet));
    }
    function syncJenis() {
        const sebagian = form?.querySelector('.pk-jenis[value="sebagian"]')?.checked;
        document.getElementById('pk-sebagian-box')?.classList.toggle('pk-hidden', !sebagian);
    }

    function addItemRow(data) {
        const node = document.getElementById('pk-item-tpl').content.firstElementChild.cloneNode(true);
        if (data) {
            node.querySelector('.pk-item-nama').value = data.nama_barang || '';
            node.querySelector('.pk-item-prod-search').value = data.product_label || '';
            node.querySelector('.pk-item-prod-id').value = data.product_id || '';
            node.querySelector('.pk-item-hal').value = data.halaman || '';
            node.querySelector('.pk-item-kls').value = data.kelas || '';
            node.querySelector('.pk-item-web').value = data.cetak_isi ?? '';
            node.querySelector('.pk-item-sheet').value = data.cetak_sheet ?? '';
        }
        itemsBody.appendChild(node);
        syncBahanColumns();
    }
    function addVersiRow(data) {
        const node = document.getElementById('pk-versi-tpl').content.firstElementChild.cloneNode(true);
        if (data) node.querySelector('.pk-versi-nama').value = data.nama || '';
        versiList.appendChild(node);
    }
    function addPjRow(data) {
        const node = document.getElementById('pk-pj-tpl').content.firstElementChild.cloneNode(true);
        if (data) { node.querySelector('.pk-pj-jabatan').value = data.jabatan || ''; node.querySelector('.pk-pj-nama').value = data.nama || ''; }
        pjList.appendChild(node);
    }

    document.getElementById('pk-add-item')?.addEventListener('click', () => addItemRow());
    document.getElementById('pk-add-versi')?.addEventListener('click', () => addVersiRow());
    document.getElementById('pk-add-pj')?.addEventListener('click', () => addPjRow());
    form?.querySelector('.pk-toggle-web')?.addEventListener('change', syncBahanColumns);
    form?.querySelector('.pk-toggle-sheet')?.addEventListener('change', syncBahanColumns);
    form?.querySelectorAll('.pk-jenis').forEach((r) => r.addEventListener('change', syncJenis));

    form?.addEventListener('click', (e) => {
        if (e.target.closest('.pk-del-item')) e.target.closest('.pk-item-row')?.remove();
        if (e.target.closest('.pk-del-versi')) e.target.closest('.pk-versi-row')?.remove();
        if (e.target.closest('.pk-del-pj')) e.target.closest('.pk-pj-row')?.remove();
    });

    // Product autocomplete (delegated within modal)
    let lookupTimer = null;
    document.addEventListener('input', (e) => {
        const input = e.target.closest('.pk-item-prod-search');
        if (!input || !PK.lookupUrl) return;
        const results = input.parentElement.querySelector('.pk-prod-results');
        const idInput = input.parentElement.querySelector('.pk-item-prod-id');
        idInput.value = '';
        const q = input.value.trim();
        clearTimeout(lookupTimer);
        if (q.length < 2) { results.style.display = 'none'; return; }
        lookupTimer = setTimeout(() => {
            fetch(PK.lookupUrl + '?search=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((json) => {
                    results.innerHTML = '';
                    (json.data || []).forEach((p) => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:6px 10px; cursor:pointer; font-size:12px;';
                        item.textContent = p.code + ' — ' + p.name + ' (stok ' + p.stock + ')';
                        item.addEventListener('click', () => { input.value = p.code + ' — ' + p.name; idInput.value = p.id; results.style.display = 'none'; });
                        results.appendChild(item);
                    });
                    results.style.display = (json.data || []).length ? 'block' : 'none';
                });
        }, 200);
    });

    form?.addEventListener('submit', () => {
        itemsBody.querySelectorAll('.pk-item-row').forEach((row, i) => {
            const set = (cls, name) => { const el = row.querySelector(cls); if (el) el.name = 'items[' + i + '][' + name + ']'; };
            set('.pk-item-nama', 'nama_barang'); set('.pk-item-prod-id', 'product_id');
            set('.pk-item-hal', 'halaman'); set('.pk-item-kls', 'kelas');
            set('.pk-item-web', 'cetak_isi'); set('.pk-item-sheet', 'cetak_sheet');
        });
        versiList.querySelectorAll('.pk-versi-row').forEach((row, i) => { const el = row.querySelector('.pk-versi-nama'); if (el) el.name = 'versi[' + i + '][nama]'; });
        pjList.querySelectorAll('.pk-pj-row').forEach((row, i) => {
            const j = row.querySelector('.pk-pj-jabatan'); const n = row.querySelector('.pk-pj-nama');
            if (j) j.name = 'pj[' + i + '][jabatan]'; if (n) n.name = 'pj[' + i + '][nama]';
        });
    });

    function resetForm() { form.reset(); itemsBody.innerHTML = ''; versiList.innerHTML = ''; pjList.innerHTML = ''; }

    function openModalForEdit(id) {
        if (!form) return;
        const d = PK.data[id];
        if (!d) return;
        resetForm();
        document.getElementById('pk-modal-title').textContent = 'Edit SPK';
        document.getElementById('pk-save-btn').textContent = 'Simpan Perubahan';
        document.getElementById('pk-form-method').value = 'PUT';
        form.action = PK.updateUrlTpl.replace('__ID__', id);
        const setV = (name, val) => { const el = form.querySelector('[name="' + name + '"]'); if (el) el.value = val ?? ''; };
        ['konsumen', 'alamat', 'jenis_order', 'tanggal_order', 'deadline_kirim', 'mesin_cover', 'finishing', 'packing', 'ukuran_jadi', 'catatan',
         'web_kertas', 'web_warna', 'web_mesin', 'web_waste', 'sheet_kertas', 'sheet_warna', 'sheet_mesin', 'sheet_waste', 'revisi_bagian'].forEach((k) => setV(k, d[k]));
        form.querySelector('[name="pakai_web"]').checked = !!d.pakai_web;
        form.querySelector('[name="pakai_sheet"]').checked = !!d.pakai_sheet;
        form.querySelector('.pk-jenis[value="' + (d.jenis_cetak === 'sebagian' ? 'sebagian' : 'penuh') + '"]').checked = true;
        (d.items || []).forEach((it) => addItemRow(it));
        if (!(d.items || []).length) addItemRow();
        (d.versi || []).forEach((v) => addVersiRow(v));
        (d.pj || []).forEach((p) => addPjRow(p));
        if (!(d.pj || []).length) addPjRow();
        syncBahanColumns(); syncJenis();
        closeDetail(); openModal();
    }

    // ---- Init ----
    readData();
    applyTab();
    applyFilters();
    if (PK.openId) openDetail(PK.openId);

    // Create page: seed one empty item + PJ row so the form is ready to fill.
    if (form && form.getAttribute('data-mode') === 'create') {
        addItemRow(); addPjRow();
        syncBahanColumns(); syncJenis();
    }
})();
</script>

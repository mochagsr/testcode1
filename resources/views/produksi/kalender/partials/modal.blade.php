{{-- Edit SPK modal (create uses a dedicated page). --}}
<div id="pk-modal-overlay" class="pk-overlay">
    <div class="pk-wrap pk-modal-box">
        <div style="background:#2457c5; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center;">
            <b>Edit SPK</b>
            <button type="button" id="pk-modal-close" class="btn secondary" style="padding:4px 10px;">Batal</button>
        </div>
        <form id="pk-spk-form" method="post" action="{{ route('produksi.spk.store') }}">
            @csrf
            <input type="hidden" name="_method" id="pk-form-method" value="PUT">
            <div class="pk-modal-scroll" style="padding:16px 20px;">
                @include('produksi.kalender.partials.form-inner')
            </div>
            <div style="padding:12px 20px; border-top:1px solid var(--border); display:flex; gap:8px;">
                <button type="submit" class="btn" id="pk-save-btn">Simpan Perubahan</button>
                <button type="button" id="pk-modal-cancel" class="btn secondary">Batal</button>
            </div>
        </form>
    </div>
</div>

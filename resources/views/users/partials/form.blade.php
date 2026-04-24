@php
    $selectedPermissions = collect(match (true) {
        old('permissions') !== null => (array) old('permissions', []),
        $user !== null => $user->resolvedPermissions(),
        default => (array) config('rbac.roles.'.old('role', 'user'), []),
    })
        ->map(fn ($permission) => strtolower((string) $permission))
        ->filter(fn (string $permission): bool => $permission !== '' && $permission !== '*')
        ->values()
        ->all();

    $hiddenPermissions = [
        'transactions.view',
        'transactions.create',
        'transactions.edit',
        'transactions.export',
        'transactions.cancel',
        'masters.products.view',
        'masters.products.manage',
        'masters.customers.view',
        'masters.customers.manage',
        'masters.suppliers.view',
        'masters.suppliers.edit',
    ];

    $groupLabelMap = [
        'dashboard' => 'Menu Dashboard',
        'sales_invoices' => 'Faktur Penjualan',
        'sales_returns' => 'Retur Penjualan',
        'delivery_notes' => 'Surat Jalan',
        'order_notes' => 'Surat Pesanan',
        'delivery_trips' => 'Catatan Perjalanan',
        'outgoing_transactions' => 'Tanda Terima Barang',
        'school_bulk_transactions' => 'Sebar Sekolah',
        'ship_locations' => 'Lokasi Kirim Sekolah',
        'receivables' => 'Menu Piutang Customer',
        'supplier_payments' => 'Pembayaran Supplier',
        'suppliers' => 'Menu Supplier',
        'reports' => 'Menu Laporan',
        'system' => 'Menu Sistem',
        'products' => 'Menu Barang',
        'customers' => 'Menu Customer',
    ];

    $permissionDetailMap = [
        'dashboard.view' => [
            'title' => 'Dashboard',
            'note' => 'Bisa membuka halaman Dashboard.',
        ],
        'transactions.view' => [
            'title' => 'Lihat menu transaksi',
            'note' => 'Faktur Penjualan, Retur Penjualan, Surat Jalan, Surat Pesanan, Catatan Perjalanan, Tanda Terima Barang, dan Sebar Sekolah.',
        ],
        'sales_invoices.create' => ['title' => 'Buat faktur penjualan', 'note' => 'Bisa membuat faktur penjualan baru.'],
        'sales_invoices.edit' => ['title' => 'Edit faktur penjualan', 'note' => 'Bisa edit dari halaman detail faktur penjualan.'],
        'sales_invoices.cancel' => ['title' => 'Batalkan faktur penjualan', 'note' => 'Bisa membatalkan faktur penjualan yang masih boleh dibatalkan.'],
        'sales_invoices.export' => ['title' => 'Print / PDF / Excel faktur penjualan', 'note' => 'Bisa cetak dan export dokumen faktur penjualan.'],
        'sales_returns.create' => ['title' => 'Buat retur penjualan', 'note' => 'Bisa membuat retur penjualan baru.'],
        'sales_returns.edit' => ['title' => 'Edit retur penjualan', 'note' => 'Bisa edit dari halaman detail retur penjualan.'],
        'sales_returns.cancel' => ['title' => 'Batalkan retur penjualan', 'note' => 'Bisa membatalkan retur penjualan yang masih boleh dibatalkan.'],
        'sales_returns.export' => ['title' => 'Print / PDF / Excel retur penjualan', 'note' => 'Bisa cetak dan export dokumen retur penjualan.'],
        'delivery_notes.create' => ['title' => 'Buat surat jalan', 'note' => 'Bisa membuat surat jalan baru.'],
        'delivery_notes.edit' => ['title' => 'Edit surat jalan', 'note' => 'Bisa edit dari halaman detail surat jalan.'],
        'delivery_notes.cancel' => ['title' => 'Batalkan surat jalan', 'note' => 'Bisa membatalkan surat jalan yang masih boleh dibatalkan.'],
        'delivery_notes.export' => ['title' => 'Print / PDF / Excel surat jalan', 'note' => 'Bisa cetak dan export surat jalan.'],
        'order_notes.create' => ['title' => 'Buat surat pesanan', 'note' => 'Bisa membuat surat pesanan baru.'],
        'order_notes.edit' => ['title' => 'Edit surat pesanan', 'note' => 'Bisa edit dari halaman detail surat pesanan.'],
        'order_notes.cancel' => ['title' => 'Batalkan surat pesanan', 'note' => 'Bisa membatalkan surat pesanan yang masih boleh dibatalkan.'],
        'order_notes.export' => ['title' => 'Print / PDF / Excel surat pesanan', 'note' => 'Bisa cetak dan export surat pesanan.'],
        'delivery_trips.create' => ['title' => 'Buat catatan perjalanan', 'note' => 'Bisa membuat catatan perjalanan baru.'],
        'delivery_trips.edit' => ['title' => 'Edit catatan perjalanan', 'note' => 'Bisa edit catatan perjalanan.'],
        'delivery_trips.export' => ['title' => 'Print / PDF / Excel catatan perjalanan', 'note' => 'Bisa cetak dan export catatan perjalanan.'],
        'outgoing_transactions.create' => ['title' => 'Buat tanda terima barang', 'note' => 'Bisa membuat tanda terima barang baru.'],
        'outgoing_transactions.edit' => ['title' => 'Edit tanda terima barang', 'note' => 'Bisa edit tanda terima barang dari halaman detail.'],
        'outgoing_transactions.export' => ['title' => 'Print / PDF / Excel tanda terima barang', 'note' => 'Bisa cetak dan export tanda terima barang.'],
        'school_bulk_transactions.create' => ['title' => 'Buat sebar sekolah', 'note' => 'Bisa membuat transaksi sebar sekolah dan generate invoice.'],
        'school_bulk_transactions.export' => ['title' => 'Print / PDF / Excel sebar sekolah', 'note' => 'Bisa cetak dan export sebar sekolah.'],
        'customer_ship_locations.create' => ['title' => 'Kelola lokasi kirim sekolah', 'note' => 'Bisa tambah, edit, hapus, dan import lokasi kirim sekolah.'],
        'transactions.correction.request' => [
            'title' => 'Ajukan koreksi transaksi',
            'note' => 'Bisa memakai Wizard Koreksi pada transaksi yang perlu diperbaiki.',
        ],
        'transactions.correction.approve' => [
            'title' => 'Approval koreksi transaksi',
            'note' => 'Menu Sistem > Approval. Bisa menyetujui, menolak, atau jalankan ulang koreksi transaksi.',
        ],
        'receivables.view' => [
            'title' => 'Lihat menu piutang customer',
            'note' => 'Piutang, Piutang Global, Piutang Semester, dan Rekap/Cetak Piutang Customer.',
        ],
        'receivables.pay' => [
            'title' => 'Input pembayaran piutang',
            'note' => 'Menu Bayar Piutang dan pembuatan kwitansi pembayaran customer.',
        ],
        'receivables.adjust' => [
            'title' => 'Penyesuaian piutang',
            'note' => 'Write-off atau diskon piutang customer.',
        ],
        'receivables.lock' => [
            'title' => 'Tutup / buka semester piutang customer',
            'note' => 'Bisa lock atau unlock semester piutang per customer.',
        ],
        'supplier_payables.view' => [
            'title' => 'Lihat menu hutang supplier',
            'note' => 'Hutang Supplier, Kartu Stok Supplier, detail pembayaran supplier, dan cetaknya.',
        ],
        'supplier_payables.pay' => [
            'title' => 'Input pembayaran hutang supplier',
            'note' => 'Menu Hutang Supplier > Bayar dan dokumen pembayaran supplier.',
        ],
        'supplier_payables.adjust' => [
            'title' => 'Tutup / buka bulan hutang supplier',
            'note' => 'Bisa lock atau unlock periode hutang supplier.',
        ],
        'reports.view' => [
            'title' => 'Lihat menu laporan',
            'note' => 'Bisa membuka halaman Laporan dan memilih data laporan.',
        ],
        'reports.export' => [
            'title' => 'Print / PDF / Excel laporan',
            'note' => 'Bisa cetak dan export laporan dari menu Laporan.',
        ],
        'settings.profile' => [
            'title' => 'Menu Pengaturan',
            'note' => 'Bisa membuka Pengaturan untuk profil/tema/bahasa sendiri.',
        ],
        'settings.admin' => [
            'title' => 'Pengaturan admin',
            'note' => 'Menu Sistem > Pengaturan, Semester Transaksi, Ops Health, dan Arsip Data untuk pengaturan tingkat admin.',
        ],
        'masters.products.view' => [
            'title' => 'Lihat menu Barang',
            'note' => 'Barang, Kategori Barang, dan Satuan Barang.',
        ],
        'products.create' => ['title' => 'Tambah barang', 'note' => 'Tambah barang baru, kategori, dan satuan barang.'],
        'products.edit' => ['title' => 'Edit barang', 'note' => 'Edit barang, ubah stok cepat, kategori, dan satuan barang.'],
        'products.delete' => ['title' => 'Hapus barang', 'note' => 'Hapus barang, kategori barang, dan satuan barang.'],
        'products.import' => ['title' => 'Import barang', 'note' => 'Import barang, kategori barang, dan satuan barang dari template.'],
        'masters.customers.view' => [
            'title' => 'Lihat menu Customer',
            'note' => 'Customer dan informasi level customer.',
        ],
        'customers.create' => ['title' => 'Tambah customer', 'note' => 'Tambah customer baru dan level customer.'],
        'customers.edit' => ['title' => 'Edit customer', 'note' => 'Edit customer dan level customer.'],
        'customers.delete' => ['title' => 'Hapus customer', 'note' => 'Hapus customer dan level customer.'],
        'customers.import' => ['title' => 'Import customer', 'note' => 'Import customer dari template.'],
        'masters.suppliers.view' => [
            'title' => 'Lihat menu Supplier',
            'note' => 'Bisa membuka daftar Supplier.',
        ],
        'suppliers.create' => ['title' => 'Tambah supplier', 'note' => 'Tambah supplier baru.'],
        'suppliers.edit' => ['title' => 'Edit supplier', 'note' => 'Edit data supplier.'],
        'suppliers.delete' => ['title' => 'Hapus supplier', 'note' => 'Hapus data supplier.'],
        'suppliers.import' => ['title' => 'Import supplier', 'note' => 'Import supplier dari template.'],
        'receivable_payments.edit' => ['title' => 'Edit pembayaran piutang', 'note' => 'Edit dokumen pembayaran piutang dari halaman detail.'],
        'receivable_payments.cancel' => ['title' => 'Batalkan pembayaran piutang', 'note' => 'Batalkan dokumen pembayaran piutang.'],
        'receivable_payments.export' => ['title' => 'Print / PDF / Excel pembayaran piutang', 'note' => 'Bisa cetak dan export pembayaran piutang.'],
        'supplier_payments.edit' => ['title' => 'Edit pembayaran supplier', 'note' => 'Edit pembayaran hutang supplier dari halaman detail.'],
        'supplier_payments.export' => ['title' => 'Print / PDF / Excel pembayaran supplier', 'note' => 'Bisa cetak dan export pembayaran supplier.'],
        'imports.transactions' => [
            'title' => 'Import Faktur Penjualan',
            'note' => 'Bisa memakai fitur import pada menu Faktur Penjualan.',
        ],
        'semester.bulk' => [
            'title' => 'Semester Transaksi',
            'note' => 'Menu Sistem > Semester Transaksi untuk aksi massal buka/tutup semester.',
        ],
        'users.manage' => [
            'title' => 'Menu Pengguna',
            'note' => 'Bisa tambah, edit, dan atur hak akses user lain di menu Sistem > Pengguna.',
        ],
        'audit_logs.view' => [
            'title' => 'Menu Audit Log',
            'note' => 'Bisa membuka menu Sistem > Audit Log.',
        ],
    ];

    $permissionUiGroupMap = [
        'dashboard.view' => 'dashboard',
        'sales_invoices.create' => 'sales_invoices',
        'sales_invoices.edit' => 'sales_invoices',
        'sales_invoices.cancel' => 'sales_invoices',
        'sales_invoices.export' => 'sales_invoices',
        'sales_returns.create' => 'sales_returns',
        'sales_returns.edit' => 'sales_returns',
        'sales_returns.cancel' => 'sales_returns',
        'sales_returns.export' => 'sales_returns',
        'delivery_notes.create' => 'delivery_notes',
        'delivery_notes.edit' => 'delivery_notes',
        'delivery_notes.cancel' => 'delivery_notes',
        'delivery_notes.export' => 'delivery_notes',
        'order_notes.create' => 'order_notes',
        'order_notes.edit' => 'order_notes',
        'order_notes.cancel' => 'order_notes',
        'order_notes.export' => 'order_notes',
        'delivery_trips.create' => 'delivery_trips',
        'delivery_trips.edit' => 'delivery_trips',
        'delivery_trips.export' => 'delivery_trips',
        'outgoing_transactions.create' => 'outgoing_transactions',
        'outgoing_transactions.edit' => 'outgoing_transactions',
        'outgoing_transactions.export' => 'outgoing_transactions',
        'school_bulk_transactions.create' => 'school_bulk_transactions',
        'school_bulk_transactions.export' => 'school_bulk_transactions',
        'customer_ship_locations.create' => 'ship_locations',
        'transactions.correction.request' => 'transactions',
        'receivables.view' => 'receivables',
        'receivables.pay' => 'receivables',
        'receivables.adjust' => 'receivables',
        'receivables.lock' => 'receivables',
        'receivable_payments.edit' => 'receivables',
        'receivable_payments.cancel' => 'receivables',
        'receivable_payments.export' => 'receivables',
        'supplier_payables.view' => 'suppliers',
        'supplier_payables.pay' => 'suppliers',
        'supplier_payables.adjust' => 'suppliers',
        'supplier_payments.edit' => 'supplier_payments',
        'supplier_payments.export' => 'supplier_payments',
        'masters.suppliers.view' => 'suppliers',
        'suppliers.create' => 'suppliers',
        'suppliers.edit' => 'suppliers',
        'suppliers.delete' => 'suppliers',
        'suppliers.import' => 'suppliers',
        'reports.view' => 'reports',
        'reports.export' => 'reports',
        'products.create' => 'products',
        'products.edit' => 'products',
        'products.delete' => 'products',
        'products.import' => 'products',
        'customers.create' => 'customers',
        'customers.edit' => 'customers',
        'customers.delete' => 'customers',
        'customers.import' => 'customers',
        'settings.profile' => 'system',
        'settings.admin' => 'system',
        'imports.transactions' => 'system',
        'semester.bulk' => 'system',
        'users.manage' => 'system',
        'audit_logs.view' => 'system',
        'transactions.correction.approve' => 'system',
    ];

    $permissionGroups = collect($availablePermissions ?? [])
        ->map(fn (string $permission): string => strtolower(trim($permission)))
        ->filter(fn (string $permission): bool => $permission !== '' && !in_array($permission, $hiddenPermissions, true))
        ->groupBy(fn (string $permission): string => $permissionUiGroupMap[$permission] ?? 'system')
        ->map(function ($permissions, string $prefix) use ($groupLabelMap): array {
            $prefixLabel = $groupLabelMap[$prefix] ?? \Illuminate\Support\Str::headline((string) $prefix);
            return [
                'key' => $prefix,
                'label' => $prefixLabel,
                'permissions' => collect($permissions)->values()->all(),
            ];
        })
        ->sortBy(fn (array $group): string => $group['label'])
        ->values();
@endphp

<style>
    .user-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 12px;
    }
    .user-form-panel {
        height: 100%;
    }
    .user-form-panel .row.inline > [class^="col-"] > input,
    .user-form-panel .row.inline > [class^="col-"] > select,
    .user-form-panel .row.inline > [class^="col-"] > textarea {
        max-width: 100%;
    }
    .permission-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 10px;
        max-height: 520px;
        overflow: auto;
        padding-right: 4px;
    }
    .permission-toolbar {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .permission-toolbar-left {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .permission-toolbar .btn {
        min-height: 30px;
        padding: 5px 10px;
        font-size: 12px;
    }
    .permission-group {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 10px;
        background: color-mix(in srgb, var(--card) 96%, var(--bg));
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .permission-group-header {
        display: grid;
        grid-template-columns: 18px minmax(0, 1fr);
        align-items: start;
        column-gap: 8px;
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 700;
    }
    .permission-item {
        display: grid;
        grid-template-columns: 18px minmax(0, 1fr);
        align-items: start;
        column-gap: 8px;
        margin: 0 0 6px;
        font-size: 12px;
        line-height: 1.35;
    }
    .permission-item-text {
        min-width: 0;
    }
    .permission-item-title {
        display: block;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 2px;
    }
    .permission-item-note {
        display: block;
        color: var(--muted);
        font-size: 11px;
        line-height: 1.4;
    }
    .permission-grid input[type="checkbox"] {
        width: 16px !important;
        max-width: 16px !important;
        min-width: 16px !important;
        height: 16px;
        margin: 1px 0 0;
        padding: 0;
        justify-self: start;
    }
    .permission-group-header span,
    .permission-item span {
        min-width: 0;
        word-break: break-word;
    }
    .permission-item:last-child {
        margin-bottom: 0;
    }
    .permission-detail-title {
        font-size: 13px;
        font-weight: 700;
        margin: 0 0 2px;
    }
    .permission-detail-note {
        margin: 0 0 8px;
        font-size: 12px;
        color: var(--muted);
    }
    .permission-hit {
        background: color-mix(in srgb, #facc15 18%, var(--card));
        border-radius: 4px;
        padding: 0 2px;
    }
    .permission-card {
        margin-top: 12px;
    }
    @media (max-width: 1100px) {
        .user-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <div class="user-form-grid">
        <div class="form-section user-form-panel">
            <h3 class="form-section-title">{{ __('ui.user_account') }}</h3>
            <p class="form-section-note">{{ __('ui.user_account_note') }}</p>
            <div class="row inline">
                <div class="col-12">
                    <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user?->name) }}" required>
                </div>
                <div class="col-12">
                    <label>{{ __('ui.username') }} <span class="label-required">*</span></label>
                    <input type="text" name="username" value="{{ old('username', $user?->username) }}" required>
                    <div class="hint">{{ __('ui.username_hint') }}</div>
                </div>
                <div class="col-12">
                    <label>{{ __('ui.email') }} <span class="label-required">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user?->email) }}" required>
                </div>
                <div class="col-12">
                    <label>{{ __('ui.password') }} @if(!$user)<span class="label-required">*</span>@endif @if($user) ({{ __('ui.new_password_optional') }}) @endif</label>
                    <input type="password" name="password" @if(!$user) required @endif>
                </div>
            </div>
        </div>

        <div class="form-section user-form-panel">
            <h3 class="form-section-title">{{ __('ui.user_access') }}</h3>
            <p class="form-section-note">{{ __('ui.user_access_note') }}</p>
            <div class="row inline">
                <div class="col-6">
                    <label>{{ __('ui.role') }} <span class="label-required">*</span></label>
                    <select id="user-role-select" name="role" required>
                        <option value="admin" @selected(old('role', $user?->role ?? 'user') === 'admin')>Admin</option>
                        <option value="user" @selected(old('role', $user?->role ?? 'user') === 'user')>User</option>
                    </select>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.language') }} <span class="label-required">*</span></label>
                    <select name="locale" required>
                        <option value="id" @selected(old('locale', $user?->locale ?? 'id') === 'id')>ID</option>
                        <option value="en" @selected(old('locale', $user?->locale ?? 'id') === 'en')>EN</option>
                    </select>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.theme') }} <span class="label-required">*</span></label>
                    <select name="theme" required>
                        <option value="light" @selected(old('theme', $user?->theme ?? 'light') === 'light')>Light</option>
                        <option value="dark" @selected(old('theme', $user?->theme ?? 'light') === 'dark')>Dark</option>
                    </select>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.finance_lock') }}</label>
                    <select name="finance_locked">
                        <option value="0" @selected((string) old('finance_locked', (int) ($user?->finance_locked ?? false)) === '0')>{{ __('ui.no') }}</option>
                        <option value="1" @selected((string) old('finance_locked', (int) ($user?->finance_locked ?? false)) === '1')>{{ __('ui.yes') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card permission-card" id="permission-grid-wrapper" style="{{ old('role', $user?->role ?? 'user') === 'admin' ? 'display:none;' : '' }}">
    <p class="permission-detail-title">Hak Akses Detail</p>
    <p class="permission-detail-note">Pilih akses berdasarkan menu yang benar-benar muncul di aplikasi. Centang judul menu untuk memilih semua akses di menu itu.</p>
    <div class="permission-toolbar">
        <div class="permission-toolbar-left">
            <input
                type="text"
                id="permission-filter-input"
                placeholder="Cari menu / submenu / akses..."
                style="max-width: 280px;"
            >
            <span id="permission-filter-count" class="muted">0 izin terlihat</span>
        </div>
        <div class="permission-toolbar-left">
            <button type="button" id="permission-check-visible" class="btn process-soft-btn">Centang Terlihat</button>
            <button type="button" id="permission-uncheck-visible" class="btn warning-btn">Hapus Centang Terlihat</button>
        </div>
    </div>
    <div class="permission-grid">
        @foreach($permissionGroups as $group)
            @php
                $groupKey = (string) ($group['key'] ?? 'other');
            @endphp
            <div class="permission-group" data-group-key="{{ strtolower($groupKey) }}" data-group-label="{{ strtolower((string) $group['label']) }}">
                <label class="permission-group-header">
                    <input type="checkbox" class="permission-group-toggle" data-group="{{ $groupKey }}">
                    <span>{{ $group['label'] }}</span>
                </label>
                @foreach(($group['permissions'] ?? []) as $permission)
                    @php
                        $permissionMeta = $permissionDetailMap[$permission] ?? [
                            'title' => \Illuminate\Support\Str::headline(str_replace('.', ' ', (string) $permission)),
                            'note' => '',
                        ];
                        $permissionLabel = (string) ($permissionMeta['title'] ?? $permission);
                        $permissionNote = (string) ($permissionMeta['note'] ?? '');
                    @endphp
                    <label class="permission-item" data-permission-label="{{ strtolower($permissionLabel.' '.$permissionNote) }}" data-permission-key="{{ strtolower((string) $permission) }}">
                        <input type="checkbox" class="permission-checkbox permission-group-{{ $groupKey }}" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                        <span class="permission-item-text">
                            <span class="permission-item-title">{{ $permissionLabel }}</span>
                            @if($permissionNote !== '')
                                <span class="permission-item-note">{{ $permissionNote }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('users.index') }}">{{ __('ui.cancel') }}</a>

<script>
    (function () {
        const roleSelect = document.getElementById('user-role-select');
        const permissionGridWrapper = document.getElementById('permission-grid-wrapper');
        if (!roleSelect || !permissionGridWrapper) {
            return;
        }
        const syncPermissionVisibility = () => {
            permissionGridWrapper.style.display = roleSelect.value === 'admin' ? 'none' : '';
        };
        roleSelect.addEventListener('change', syncPermissionVisibility);
        syncPermissionVisibility();

        const syncGroupToggleState = (groupName) => {
            const toggle = document.querySelector('.permission-group-toggle[data-group="' + CSS.escape(groupName) + '"]');
            if (!toggle) {
                return;
            }
            const checkboxes = document.querySelectorAll('.permission-group-' + CSS.escape(groupName));
            const checkedCount = Array.from(checkboxes).filter((cb) => cb.checked).length;
            if (checkedCount === 0) {
                toggle.checked = false;
                toggle.indeterminate = false;
                return;
            }
            if (checkedCount === checkboxes.length) {
                toggle.checked = true;
                toggle.indeterminate = false;
                return;
            }
            toggle.checked = false;
            toggle.indeterminate = true;
        };

        const groupToggles = document.querySelectorAll('.permission-group-toggle');
        groupToggles.forEach((toggle) => {
            toggle.addEventListener('change', () => {
                const groupName = toggle.getAttribute('data-group');
                if (!groupName) {
                    return;
                }
                const checkboxes = document.querySelectorAll('.permission-group-' + CSS.escape(groupName));
                checkboxes.forEach((cb) => {
                    cb.checked = toggle.checked;
                });
                syncGroupToggleState(groupName);
            });
        });

        const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
        permissionCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const classes = Array.from(checkbox.classList);
                const groupClass = classes.find((className) => className.startsWith('permission-group-'));
                if (!groupClass) {
                    return;
                }
                const groupName = groupClass.replace('permission-group-', '');
                syncGroupToggleState(groupName);
            });
        });

        groupToggles.forEach((toggle) => {
            const groupName = toggle.getAttribute('data-group');
            if (groupName) {
                syncGroupToggleState(groupName);
            }
        });

        const filterInput = document.getElementById('permission-filter-input');
        const filterCount = document.getElementById('permission-filter-count');
        const checkVisibleBtn = document.getElementById('permission-check-visible');
        const uncheckVisibleBtn = document.getElementById('permission-uncheck-visible');
        const groups = document.querySelectorAll('.permission-group');

        const updateVisibleCount = () => {
            const visibleItems = document.querySelectorAll('.permission-item[data-visible="1"]');
            if (filterCount) {
                filterCount.textContent = visibleItems.length + ' izin terlihat';
            }
        };

        const clearHighlights = (textNode) => {
            if (!textNode) return;
            textNode.innerHTML = textNode.textContent || '';
        };

        const markHighlight = (textNode, keyword) => {
            if (!textNode) return;
            const plain = textNode.textContent || '';
            if (!keyword) {
                textNode.innerHTML = plain;
                return;
            }
            const escaped = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp('(' + escaped + ')', 'ig');
            textNode.innerHTML = plain.replace(regex, '<span class="permission-hit">$1</span>');
        };

        const applyFilter = () => {
            const keyword = String(filterInput?.value || '').trim().toLowerCase();
            groups.forEach((group) => {
                const groupLabel = String(group.getAttribute('data-group-label') || '');
                const groupKey = String(group.getAttribute('data-group-key') || '');
                const items = group.querySelectorAll('.permission-item');
                let hasVisible = false;

                items.forEach((item) => {
                    const itemLabel = String(item.getAttribute('data-permission-label') || '');
                    const itemKey = String(item.getAttribute('data-permission-key') || '');
                    const textNode = item.querySelector('.permission-item-text');
                    const match = keyword === ''
                        || groupLabel.includes(keyword)
                        || groupKey.includes(keyword)
                        || itemLabel.includes(keyword)
                        || itemKey.includes(keyword);
                    item.style.display = match ? '' : 'none';
                    item.setAttribute('data-visible', match ? '1' : '0');
                    if (match) {
                        hasVisible = true;
                        markHighlight(textNode, keyword);
                    } else {
                        clearHighlights(textNode);
                    }
                });

                group.style.display = hasVisible ? '' : 'none';
            });
            updateVisibleCount();
        };

        checkVisibleBtn?.addEventListener('click', () => {
            document.querySelectorAll('.permission-item[data-visible="1"] .permission-checkbox').forEach((checkbox) => {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        uncheckVisibleBtn?.addEventListener('click', () => {
            document.querySelectorAll('.permission-item[data-visible="1"] .permission-checkbox').forEach((checkbox) => {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        filterInput?.addEventListener('input', applyFilter);
        applyFilter();
    })();
</script>

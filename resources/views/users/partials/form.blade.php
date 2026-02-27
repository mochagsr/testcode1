@php
    $selectedPermissions = collect(old('permissions', (array) ($user?->permissions ?? [])))
        ->map(fn ($permission) => strtolower((string) $permission))
        ->all();

    $groupLabelMap = [
        'dashboard' => 'Dashboard',
        'transactions' => 'Transaksi',
        'receivables' => 'Piutang Customer',
        'supplier_payables' => 'Hutang Supplier',
        'reports' => 'Laporan',
        'settings' => 'Pengaturan',
        'masters' => 'Master Data',
        'imports' => 'Import Data',
        'semester' => 'Semester',
        'users' => 'Manajemen User',
        'audit_logs' => 'Audit Log',
    ];

    $permissionLabelMap = [
        'dashboard.view' => 'Lihat dashboard',
        'transactions.view' => 'Lihat transaksi',
        'transactions.create' => 'Buat transaksi',
        'transactions.export' => 'Export transaksi',
        'transactions.cancel' => 'Batalkan transaksi',
        'transactions.correction.request' => 'Ajukan koreksi transaksi',
        'transactions.correction.approve' => 'Setujui koreksi transaksi',
        'receivables.view' => 'Lihat piutang customer',
        'receivables.pay' => 'Input pembayaran piutang',
        'receivables.adjust' => 'Write-off / diskon piutang',
        'supplier_payables.view' => 'Lihat hutang supplier',
        'supplier_payables.pay' => 'Input pembayaran hutang supplier',
        'supplier_payables.adjust' => 'Penyesuaian hutang supplier',
        'reports.view' => 'Lihat laporan',
        'reports.export' => 'Export laporan',
        'settings.profile' => 'Ubah profil sendiri',
        'settings.admin' => 'Ubah pengaturan admin',
        'masters.products.view' => 'Lihat barang',
        'masters.products.manage' => 'Kelola barang',
        'masters.customers.view' => 'Lihat customer',
        'masters.customers.manage' => 'Kelola customer',
        'masters.suppliers.view' => 'Lihat supplier',
        'masters.suppliers.edit' => 'Kelola supplier',
        'imports.transactions' => 'Import transaksi',
        'semester.bulk' => 'Buka/tutup semester massal',
        'users.manage' => 'Kelola user',
        'audit_logs.view' => 'Lihat audit log',
    ];

    $permissionGroups = collect($availablePermissions ?? [])
        ->map(fn (string $permission): string => strtolower(trim($permission)))
        ->filter(fn (string $permission): bool => $permission !== '')
        ->groupBy(fn (string $permission): string => explode('.', $permission)[0] ?? 'other')
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
        max-height: 460px;
        overflow: auto;
        padding-right: 4px;
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
    <p class="permission-detail-note">Pilih akses per modul. Centang judul modul untuk pilih semua di modul tersebut.</p>
    <div class="permission-grid">
        @foreach($permissionGroups as $group)
            @php
                $groupKey = (string) ($group['key'] ?? 'other');
            @endphp
            <div class="permission-group">
                <label class="permission-group-header">
                    <input type="checkbox" class="permission-group-toggle" data-group="{{ $groupKey }}">
                    <span>{{ $group['label'] }}</span>
                </label>
                @foreach(($group['permissions'] ?? []) as $permission)
                    @php
                        $permissionLabel = $permissionLabelMap[$permission]
                            ?? \Illuminate\Support\Str::headline(str_replace('.', ' ', (string) $permission));
                    @endphp
                    <label class="permission-item">
                        <input type="checkbox" class="permission-checkbox permission-group-{{ $groupKey }}" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                        <span>{{ $permissionLabel }}</span>
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
    })();
</script>

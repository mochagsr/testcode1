@extends('layouts.app')

@section('title', __('ui.customers_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .customers-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .customers-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1 1 620px;
            margin: 0;
        }
        .customers-toolbar .search-form input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 260px;
            min-width: 0;
        }
        .customers-toolbar .search-form select {
            width: 250px;
            max-width: min(250px, 100%);
            margin-left: 0;
        }
        .customers-toolbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            margin-left: auto;
            flex: 0 0 auto;
        }
        .customer-import-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10020;
            background: rgba(15, 23, 42, 0.58);
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .customer-import-overlay.is-open {
            display: flex;
        }
        .customer-import-modal {
            width: min(520px, 96vw);
            max-height: 90vh;
            overflow: auto;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
            padding: 18px;
        }
        .customer-import-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }
        .customer-import-modal-title {
            font-size: 18px;
            font-weight: 800;
        }
        .customer-import-file-wrap {
            padding: 12px;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: color-mix(in srgb, var(--card) 92%, var(--background) 8%);
        }
        .customer-import-file-wrap input[type="file"] {
            width: 100%;
        }
        .customer-import-modal-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 14px;
        }
        .customers-table-wrap {
            overflow-x: auto;
        }
        .customers-table {
            min-width: 1180px;
            table-layout: auto;
        }
        .customers-table th.ktp-col,
        .customers-table td.ktp-col {
            width: 150px;
            text-align: center;
            white-space: nowrap;
        }
        .customers-table th.action-col,
        .customers-table td.action-col {
            width: 150px;
            text-align: center;
            white-space: nowrap;
        }
        .customers-table .compact-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: nowrap;
        }
        .customers-table .compact-actions .btn {
            min-height: 30px;
            padding: 5px 11px;
            line-height: 1.2;
        }
        @media (max-width: 1400px) {
            .customers-toolbar {
                align-items: flex-start;
            }
        }
        @media (max-width: 1280px) {
            .customers-toolbar-actions {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }
            .customers-toolbar .search-form input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
    </style>
    @php
        $currentUser = auth()->user();
        $canCreateCustomers = $currentUser?->canAccess('customers.create') ?? false;
        $canManageCustomers = $currentUser?->canAccessAny(['customers.create', 'customers.edit', 'customers.delete', 'customers.import']) ?? false;
        $canImportCustomers = $currentUser?->canAccess('customers.import') ?? false;
        $customerExportQuery = ['search' => $search, 'level_id' => $selectedLevelId ?: null];
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.customers_title') }}</h1>
        @if($canCreateCustomers)
            <a class="btn" href="{{ route('customers-web.create') }}">{{ __('ui.add_customer') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="customers-toolbar">
            <form id="customers-search-form" method="get" class="search-form">
                <input id="customers-search-input" type="text" name="search" placeholder="{{ __('ui.search_customers_placeholder') }}" value="{{ $search }}">
                <select name="level_id" id="customers-level-filter">
                    <option value="">{{ __('ui.all_levels') }}</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->id }}" @selected((int) $selectedLevelId === (int) $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
                <button type="submit">{{ __('ui.search') }}</button>
            </form>
            <div class="customers-toolbar-actions">
                @if($canImportCustomers)
                    <button type="button" class="btn process-btn" id="customer-import-open">Import Data</button>
                @endif
                <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                    <option value="" selected disabled>Export</option>
                    <option value="{{ route('customers-web.export.pdf', $customerExportQuery) }}">Export PDF</option>
                    <option value="{{ route('customers-web.export.csv', $customerExportQuery) }}">Export Excel</option>
                </select>
            </div>
        </div>
        @if(session('import_errors'))
            <div class="card" style="margin-top:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.4);">
                <strong>Error Import:</strong>
                <ul style="margin:8px 0 0 18px;">
                    @foreach(array_slice((array) session('import_errors'), 0, 20) as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if($canImportCustomers)
        <div class="customer-import-overlay" id="customer-import-modal" aria-hidden="true">
            <div class="customer-import-modal" role="dialog" aria-modal="true" aria-labelledby="customer-import-title">
                <div class="customer-import-modal-head">
                    <div>
                        <div class="customer-import-modal-title" id="customer-import-title">Import Data Customer</div>
                        <div class="muted">Upload file Excel/CSV. Gunakan template kalau format file belum sesuai.</div>
                    </div>
                    <button type="button" class="btn info-btn" id="customer-import-close" style="min-height:32px; padding:5px 11px;">{{ __('ui.cancel') }}</button>
                </div>
                <form method="post" action="{{ route('customers-web.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="customer-import-file-wrap">
                        <label for="customer-import-file">File Import</label>
                        <input id="customer-import-file" type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                    </div>
                    <div class="customer-import-modal-actions">
                        <a class="btn info-btn" href="{{ route('customers-web.import.template') }}">Download Template</a>
                        <button type="submit" class="btn process-btn">Import</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="customers-table-wrap">
        <table class="customers-table">
            <thead>
            <tr>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.level') }}</th>
                <th>{{ __('ui.phone') }}</th>
                <th>{{ __('ui.city') }}</th>
                <th>{{ __('ui.address') }}</th>
                <th>{{ __('ui.receivable') }}</th>
                <th class="ktp-col">{{ __('ui.id_card') }}</th>
                <th class="action-col">{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>
                        @if($customer->level)
                            <a href="#"
                               class="customer-level-link"
                               data-level-id="{{ (int) $customer->level->id }}"
                               data-level-label="{{ $customer->level->name }}">
                                {{ $customer->level->name }}
                            </a>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @php
                            $phoneDisplay = collect([
                                (string) ($customer->phone ?? ''),
                                (string) ($customer->phone_secondary ?? ''),
                            ])->map(fn (string $value) => trim($value))->filter()->values();
                        @endphp
                        {{ $phoneDisplay->isNotEmpty() ? $phoneDisplay->implode(' / ') : '-' }}
                    </td>
                    <td>{{ $customer->city ?: '-' }}</td>
                    <td>{{ $customer->address ?: '-' }}</td>
                    <td>Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                    <td class="ktp-col">
                        @if($customer->id_card_photo_path)
                            <div class="compact-actions">
                                <a class="btn info-btn id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                                <a class="btn info-btn" href="{{ route('customers-web.id-card-photo.print', $customer) }}" target="_blank">{{ __('txn.print') }}</a>
                            </div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="action-col">
                        @if($canManageCustomers)
                            <div class="compact-actions">
                                <a class="btn edit-btn" href="{{ route('customers-web.edit', $customer) }}">{{ __('ui.edit') }}</a>
                                <form method="post" action="{{ route('customers-web.destroy', $customer) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_customer') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                                </form>
                            </div>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">{{ __('ui.no_customers') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        <div style="margin-top: 12px;">
            {{ $customers->links() }}
        </div>
    </div>

    <div id="id-card-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
        <img id="id-card-modal-image" src="" alt="ID Card" style="max-width:25vw; max-height:25vh; width:auto; height:auto; border:2px solid #fff; border-radius:8px; background:#fff;">
    </div>
    <div id="customer-level-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:10000; align-items:center; justify-content:center; padding:16px;">
        <div class="card" style="width:min(920px, 96vw); max-height:90vh; overflow:auto;">
            <div class="flex" style="justify-content:space-between; align-items:center;">
                <div id="customer-level-modal-title" style="font-size:1.2rem; font-weight:700;">{{ __('ui.customer_level') }}</div>
                <button type="button" id="customer-level-modal-close" class="btn info-btn">{{ __('ui.cancel') }}</button>
            </div>
            <table style="margin-top:12px;">
                <thead>
                <tr>
                    <th>{{ __('ui.name') }}</th>
                    <th>{{ __('ui.city') }}</th>
                    <th>{{ __('ui.address') }}</th>
                    <th>{{ __('ui.phone') }}</th>
                </tr>
                </thead>
                <tbody id="customer-level-modal-body">
                <tr><td colspan="4" class="muted">{{ __('ui.loading') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const searchForm = document.getElementById('customers-search-form');
            const searchInput = document.getElementById('customers-search-input');
            const modal = document.getElementById('id-card-modal');
            const modalImage = document.getElementById('id-card-modal-image');
            const triggers = document.querySelectorAll('.id-card-preview-trigger');
            const importModal = document.getElementById('customer-import-modal');
            const importOpen = document.getElementById('customer-import-open');
            const importClose = document.getElementById('customer-import-close');
            if (searchForm && searchInput) {
                const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                    ? window.PgposAutoSearch.debounce
                    : (fn, wait = 100) => {
                        let timeoutId = null;
                        return (...args) => {
                            clearTimeout(timeoutId);
                            timeoutId = setTimeout(() => fn(...args), wait);
                        };
                    };
                const onSearchInput = debounce(() => {
                    if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                        return;
                    }
                    searchForm.requestSubmit();
                }, 100);
                searchInput.addEventListener('input', onSearchInput);
            }
            if (importModal && importOpen && importClose) {
                const closeImportModal = () => {
                    importModal.classList.remove('is-open');
                    importModal.setAttribute('aria-hidden', 'true');
                };
                importOpen.addEventListener('click', () => {
                    importModal.classList.add('is-open');
                    importModal.setAttribute('aria-hidden', 'false');
                    document.getElementById('customer-import-file')?.focus();
                });
                importClose.addEventListener('click', closeImportModal);
                importModal.addEventListener('click', (event) => {
                    if (event.target === importModal) {
                        closeImportModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && importModal.classList.contains('is-open')) {
                        closeImportModal();
                    }
                });
            }
            if (!modal || !modalImage || !triggers.length) {
                return;
            }

            function closeModal() {
                modal.style.display = 'none';
                modalImage.setAttribute('src', '');
            }

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const image = trigger.getAttribute('data-image');
                    if (!image) {
                        return;
                    }
                    modalImage.setAttribute('src', image);
                    modal.style.display = 'flex';
                });
            });

            modal.addEventListener('click', closeModal);
            modalImage.addEventListener('click', closeModal);
        })();
    </script>
    <script>
        (function () {
            const levelLinks = document.querySelectorAll('.customer-level-link');
            const levelModal = document.getElementById('customer-level-modal');
            const levelModalClose = document.getElementById('customer-level-modal-close');
            const levelModalTitle = document.getElementById('customer-level-modal-title');
            const levelModalBody = document.getElementById('customer-level-modal-body');
            const endpointTemplate = @json(route('customers-web.level-customers', ['customerLevel' => '__LEVEL__']));

            if (!levelLinks.length || !levelModal || !levelModalClose || !levelModalTitle || !levelModalBody) {
                return;
            }

            const escapeHtml = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const closeLevelModal = () => {
                levelModal.style.display = 'none';
                levelModalBody.innerHTML = '<tr><td colspan="4" class="muted">{{ __('ui.loading') }}</td></tr>';
            };

            const renderRows = (rows) => {
                if (!Array.isArray(rows) || rows.length === 0) {
                    levelModalBody.innerHTML = '<tr><td colspan="4" class="muted">{{ __('ui.no_customers') }}</td></tr>';
                    return;
                }
                levelModalBody.innerHTML = rows.map((row) => `
                    <tr>
                        <td>${escapeHtml(row.name || '-')}</td>
                        <td>${escapeHtml(row.city || '-')}</td>
                        <td>${escapeHtml(row.address || '-')}</td>
                        <td>${escapeHtml([row.phone || '', row.phone_secondary || ''].filter((value) => String(value).trim() !== '').join(' / ') || '-')}</td>
                    </tr>
                `).join('');
            };

            const openLevelModal = async (levelId, levelLabel) => {
                if (!levelId) {
                    return;
                }
                levelModalTitle.textContent = `{{ __('ui.customer_level') }}: ${levelLabel || '-'}`;
                levelModalBody.innerHTML = '<tr><td colspan="4" class="muted">{{ __('ui.loading') }}</td></tr>';
                levelModal.style.display = 'flex';
                try {
                    const endpoint = endpointTemplate.replace('__LEVEL__', encodeURIComponent(String(levelId)));
                    const response = await fetch(endpoint, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!response.ok) {
                        levelModalBody.innerHTML = '<tr><td colspan="4" class="muted">{{ __('ui.no_data') }}</td></tr>';
                        return;
                    }
                    const payload = await response.json();
                    renderRows(payload.customers || []);
                } catch (error) {
                    levelModalBody.innerHTML = '<tr><td colspan="4" class="muted">{{ __('ui.no_data') }}</td></tr>';
                }
            };

            levelLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    openLevelModal(link.dataset.levelId, link.dataset.levelLabel);
                });
            });
            levelModalClose.addEventListener('click', closeLevelModal);
            levelModal.addEventListener('click', (event) => {
                if (event.target === levelModal) {
                    closeLevelModal();
                }
            });
        })();
    </script>
@endsection


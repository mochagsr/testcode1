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
        .customers-toolbar .toolbar-left,
        .customers-toolbar .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .customers-toolbar .toolbar-left {
            flex: 1 1 420px;
        }
        .customers-toolbar .toolbar-right {
            justify-content: flex-end;
            flex: 1 1 640px;
        }
        .customers-toolbar .search-form,
        .customers-toolbar .import-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
        }
        .customers-toolbar .search-form {
            width: 100%;
            max-width: 680px;
        }
        .customers-toolbar .search-form input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 240px;
            min-width: 0;
        }
        .customers-toolbar .search-form select {
            width: 250px;
            max-width: min(250px, 100%);
            margin-left: 0;
        }
        .customers-toolbar .import-form {
            justify-content: flex-end;
            width: 100%;
            max-width: 100%;
            gap: 12px;
        }
        .customers-toolbar .import-file-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: color-mix(in srgb, var(--card) 92%, var(--background) 8%);
            flex: 0 1 320px;
            min-width: 280px;
        }
        .customers-toolbar .import-file-wrap input[type="file"] {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            flex: 1 1 auto;
        }
        .customers-toolbar .import-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .customers-table-wrap {
            overflow-x: auto;
        }
        .customers-table {
            min-width: 1180px;
        }
        @media (max-width: 1400px) {
            .customers-toolbar .toolbar-left,
            .customers-toolbar .toolbar-right {
                flex: 1 1 100%;
            }
            .customers-toolbar .toolbar-right {
                justify-content: flex-start;
            }
            .customers-toolbar .import-form {
                justify-content: flex-start;
            }
            .customers-toolbar .import-file-wrap {
                flex-basis: 300px;
            }
        }
        @media (max-width: 1280px) {
            .customers-toolbar .search-form,
            .customers-toolbar .import-form {
                width: 100%;
            }
            .customers-toolbar .search-form input[type="text"],
            .customers-toolbar .import-file-wrap {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
            .customers-toolbar .import-actions {
                flex: 1 1 100%;
            }
        }
    </style>
    @php
        $canManageCustomers = auth()->user()?->canAccessAny(['customers.create', 'customers.edit', 'customers.delete', 'customers.import']) ?? false;
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.customers_title') }}</h1>
        @if($canManageCustomers)
            <a class="btn" href="{{ route('customers-web.create') }}">{{ __('ui.add_customer') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="customers-toolbar">
            <div class="toolbar-left">
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
            </div>
            <div class="toolbar-right">
                @if($canManageCustomers)
                    <form method="post" action="{{ route('customers-web.import') }}" enctype="multipart/form-data" class="import-form">
                        @csrf
                        <div class="import-file-wrap">
                            <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="import-actions">
                            <button type="submit" class="btn process-btn">Import</button>
                            <a class="btn info-btn" href="{{ route('customers-web.import.template') }}">Template Import</a>
                            <a class="btn info-btn" href="{{ route('customers-web.export.csv', ['search' => $search, 'level_id' => $selectedLevelId ?: null]) }}">Export Excel</a>
                        </div>
                    </form>
                @else
                    <div class="import-actions">
                        <a class="btn info-btn" href="{{ route('customers-web.export.csv', ['search' => $search, 'level_id' => $selectedLevelId ?: null]) }}">Export Excel</a>
                    </div>
                @endif
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
                <th>{{ __('ui.id_card') }}</th>
                <th>{{ __('ui.actions') }}</th>
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
                    <td>
                        @if($customer->id_card_photo_path)
                            <div class="flex">
                                <a class="btn info-btn id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                                <a class="btn info-btn" href="{{ route('customers-web.id-card-photo.print', $customer) }}" target="_blank">{{ __('txn.print') }}</a>
                            </div>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($canManageCustomers)
                            <div class="flex">
                                <a class="btn edit-btn" href="{{ route('customers-web.edit', $customer) }}">{{ __('ui.edit') }}</a>
                                <form method="post" action="{{ route('customers-web.destroy', $customer) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_customer') }}');">
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


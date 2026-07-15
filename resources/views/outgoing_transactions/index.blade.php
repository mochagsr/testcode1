@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .outgoing-scroll-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 420px;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
            scrollbar-gutter: stable;
        }
        .outgoing-scroll-wrap table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .outgoing-transactions-table,
        .outgoing-recap-table {
            width: 100%;
            border-collapse: collapse;
        }
        .outgoing-transactions-table {
            min-width: 980px;
            table-layout: fixed;
        }
        .outgoing-recap-table {
            min-width: 640px;
            table-layout: fixed;
        }
        .outgoing-transactions-table td,
        .outgoing-transactions-table th,
        .outgoing-recap-table td,
        .outgoing-recap-table th {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .outgoing-transactions-table td.num,
        .outgoing-transactions-table th.num,
        .outgoing-recap-table td.num,
        .outgoing-recap-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .outgoing-transactions-table td.action,
        .outgoing-transactions-table th.action {
            text-align: center;
            white-space: nowrap;
            width: 8%;
            min-width: 86px;
        }
        .outgoing-transactions-table .action-menu.action-menu-sm {
            min-width: 92px;
            max-width: 92px;
            padding: 5px 8px;
            font-size: 11px;
            min-height: 30px;
        }
        .outgoing-transactions-table td.num,
        .outgoing-transactions-table th.num,
        .outgoing-recap-table td.num,
        .outgoing-recap-table th.num {
            overflow: visible;
            text-overflow: clip;
        }
        .outgoing-transactions-table td.supplier-col,
        .outgoing-transactions-table th.supplier-col {
            white-space: normal;
            max-width: 0;
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
        .outgoing-main-grid .outgoing-col-list {
            grid-column: span 8;
        }
        .outgoing-main-grid .outgoing-col-recap {
            grid-column: span 4;
        }
        @media (max-width: 1366px) {
            .outgoing-main-grid .outgoing-col-list,
            .outgoing-main-grid .outgoing-col-recap {
                grid-column: span 12;
            }
        }
    </style>

    @php
        $canCreateOutgoingTransaction = auth()->user()?->canAccess('outgoing_transactions.create') ?? false;
    @endphp
    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.outgoing_transactions_title') }}</h1>
        <div class="actions">
            @if($canCreateOutgoingTransaction)
                <a class="btn create-transaction-btn" href="{{ route('outgoing-transactions.create') }}">{{ __('txn.create_outgoing_transaction') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <form id="outgoing-filter-form" method="get" class="filter-toolbar">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="filter-field">
                <label for="outgoing-search-input">{{ __('txn.search') }}</label>
                <input id="outgoing-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('txn.search_outgoing_placeholder') }}" style="max-width: 320px;">
            </div>
            <div class="filter-field">
                <label for="outgoing-date-input">{{ __('txn.date') }}</label>
                <input id="outgoing-date-input" type="date" name="transaction_date" value="{{ $selectedTransactionDate ?? '' }}" style="max-width: 150px;">
            </div>
            <div class="filter-field">
                <label for="outgoing-semester-input">{{ __('txn.semester_period') }}</label>
                <select id="outgoing-semester-input" name="semester" style="max-width: 150px;">
                    <option value="">{{ __('txn.all_semesters') }}</option>
                    @foreach($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="outgoing-year-input">{{ __('txn.year') }}</label>
                <select id="outgoing-year-input" name="year" style="max-width: 120px;">
                    <option value="">{{ __('txn.all_years') }}</option>
                    @foreach($yearOptions as $yearOption)
                        <option value="{{ $yearOption }}" @selected($selectedYear === $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="outgoing-supplier-input">{{ __('txn.supplier') }}</label>
                <select id="outgoing-supplier-input" name="supplier_id" style="max-width: 260px;">
                    <option value="">{{ __('txn.all_suppliers') }}</option>
                    @foreach($supplierOptions as $supplierOption)
                        <option value="{{ $supplierOption->id }}" @selected((int) $selectedSupplierId === (int) $supplierOption->id)>
                            {{ $supplierOption->name }}{{ $supplierOption->company_name ? ' ('.$supplierOption->company_name.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
    </div>

    <div id="outgoing-results">
        @include('outgoing_transactions.partials.results')
    </div>

    <div id="id-card-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
        <img id="id-card-modal-image" src="" alt="{{ __('supplier_payable.supplier_invoice_photo') }}" style="max-width:92vw; max-height:92vh; width:auto; height:auto; border:2px solid #fff; border-radius:8px; background:#fff;">
    </div>

    <script>
        (function () {
            const modal = document.getElementById('id-card-modal');
            const modalImage = document.getElementById('id-card-modal-image');
            if (!modal || !modalImage) {
                return;
            }

            function closeModal() {
                modal.style.display = 'none';
                modalImage.setAttribute('src', '');
            }

            // Delegated so the buttons keep working after the AJAX filter swaps the list.
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.id-card-preview-trigger');
                if (!trigger) {
                    return;
                }
                event.preventDefault();
                const image = trigger.getAttribute('data-image');
                if (!image) {
                    return;
                }
                modalImage.setAttribute('src', image);
                modal.style.display = 'flex';
            });

            modal.addEventListener('click', closeModal);
            modalImage.addEventListener('click', closeModal);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        })();

        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'outgoing-filter-form',
                container: 'outgoing-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('outgoing-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('outgoing-date-input'),
                document.getElementById('outgoing-semester-input'),
                document.getElementById('outgoing-year-input'),
                document.getElementById('outgoing-supplier-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection


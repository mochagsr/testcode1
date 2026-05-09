@extends('layouts.app')

@section('title', __('txn.detail').' '.$product->name.' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $canManageProducts = auth()->user()?->canAccessAny(['products.create', 'products.edit', 'products.delete', 'products.import']) ?? false;
        $supplierStock = (int) $supplierRows->sum('total_quantity');
        $stockUnit = $product->unit ?: '-';
    @endphp

    <style>
        .product-detail-header {
            align-items: center;
            gap: 10px;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .product-detail-actions {
            gap: 8px;
            justify-content: flex-end;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: 120px minmax(220px, 1.5fr) minmax(140px, 1fr) 110px;
            gap: 18px 26px;
        }

        .product-info-card {
            min-width: 0;
        }

        .product-info-label {
            display: block;
            color: color-mix(in srgb, var(--text) 84%, var(--muted) 16%);
            font-weight: 800;
            margin-bottom: 4px;
        }

        .product-info-value {
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .product-stock-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 18px;
        }

        .product-stock-pill {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            background: color-mix(in srgb, var(--card) 86%, var(--background) 14%);
        }

        .product-stock-pill strong {
            display: block;
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .product-stock-pill span {
            display: block;
            font-size: 18px;
            font-weight: 900;
            margin-top: 4px;
        }

        .product-supplier-table {
            table-layout: fixed;
            min-width: 900px;
        }

        .product-supplier-table th,
        .product-supplier-table td {
            vertical-align: middle;
        }

        .product-supplier-table .supplier-col {
            width: 28%;
        }

        .product-supplier-table .qty-col {
            width: 14%;
            white-space: nowrap;
        }

        .product-supplier-table .price-col {
            width: 16%;
            white-space: nowrap;
        }

        .product-supplier-table .date-col {
            width: 15%;
            white-space: nowrap;
        }

        .product-supplier-table .receipt-col {
            width: 17%;
            overflow-wrap: anywhere;
        }

        .product-supplier-table .action-col {
            width: 10%;
            text-align: right;
            white-space: nowrap;
        }

        .product-supplier-table .supplier-stock-link {
            padding: 8px 10px;
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .product-info-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .product-detail-header,
            .product-detail-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .product-info-grid,
            .product-stock-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="flex product-detail-header">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.detail') }} Barang</h1>
        <div class="flex product-detail-actions">
            <a class="btn secondary" href="{{ route('products.index') }}">{{ __('txn.back') }}</a>
            <a class="btn process-btn" href="{{ route('products.mutations', $product) }}">{{ __('ui.stock_mutations_title') }}</a>
            @if($canManageProducts)
                <a class="btn edit-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit_product') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Informasi Barang</h3>
        <div class="product-info-grid">
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.code') }}</strong>
                <div class="product-info-value">{{ $product->code ?: '-' }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.name') }}</strong>
                <div class="product-info-value">{{ $product->name }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.category') }}</strong>
                <div class="product-info-value">{{ $product->category?->name ?: '-' }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.unit') }}</strong>
                <div class="product-info-value">{{ $stockUnit }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.price_agent') }}</strong>
                <div class="product-info-value">Rp {{ number_format((int) round((float) $product->price_agent), 0, ',', '.') }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.price_sales') }}</strong>
                <div class="product-info-value">Rp {{ number_format((int) round((float) $product->price_sales), 0, ',', '.') }}</div>
            </div>
            <div class="product-info-card">
                <strong class="product-info-label">{{ __('ui.price_general') }}</strong>
                <div class="product-info-value">Rp {{ number_format((int) round((float) $product->price_general), 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="product-stock-summary">
            <div class="product-stock-pill">
                <strong>Stok Total</strong>
                <span>{{ number_format((int) round((float) $product->stock), 0, ',', '.') }} {{ $stockUnit }}</span>
            </div>
            <div class="product-stock-pill">
                <strong>Stok Awal</strong>
                <span>{{ number_format($initialStock, 0, ',', '.') }} {{ $stockUnit }}</span>
            </div>
            <div class="product-stock-pill">
                <strong>Total Masuk Supplier</strong>
                <span>{{ number_format($supplierStock, 0, ',', '.') }} {{ $stockUnit }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Supplier Barang Ini</h3>
        <p class="muted" style="margin-top: 0;">Ringkasan supplier yang pernah memasok barang ini dari data tanda terima barang.</p>
        <div style="overflow-x:auto;">
            <table class="product-supplier-table">
                <thead>
                <tr>
                    <th class="supplier-col">{{ __('txn.supplier') }}</th>
                    <th class="qty-col">{{ __('supplier_stock.total_in') }}</th>
                    <th class="price-col">Harga Beli Terakhir</th>
                    <th class="date-col">Tanggal Terakhir Beli</th>
                    <th class="receipt-col">Tanda Terima Terakhir</th>
                    <th class="action-col">{{ __('txn.action') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($supplierRows as $row)
                    <tr>
                        <td class="supplier-col">
                            <strong>{{ $row['supplier_name'] }}</strong>
                            @if($row['supplier_company_name'] !== '')
                                <div class="muted">{{ $row['supplier_company_name'] }}</div>
                            @endif
                        </td>
                        <td class="qty-col">{{ number_format((int) $row['total_quantity'], 0, ',', '.') }} {{ $row['last_unit'] ?: $stockUnit }}</td>
                        <td class="price-col">Rp {{ number_format((int) $row['last_unit_cost'], 0, ',', '.') }}</td>
                        <td class="date-col">
                            {{ $row['last_transaction_date'] ? \Illuminate\Support\Carbon::parse($row['last_transaction_date'])->format('d-m-Y') : '-' }}
                        </td>
                        <td class="receipt-col">
                            @if((int) $row['last_transaction_id'] > 0 && $row['last_transaction_number'] !== '')
                                <a href="{{ route('outgoing-transactions.show', $row['last_transaction_id']) }}">{{ $row['last_transaction_number'] }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="action-col">
                            <a class="btn process-soft-btn supplier-stock-link" href="{{ route('supplier-stock-cards.index', ['supplier_id' => $row['supplier_id'], 'product_id' => $product->id]) }}">
                                Kartu Stok
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Belum ada supplier yang memasok barang ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

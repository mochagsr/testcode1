@extends('layouts.app')

@section('title', __('txn.detail').' '.$product->name.' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $canManageProducts = auth()->user()?->canAccessAny(['products.create', 'products.edit', 'products.delete', 'products.import']) ?? false;
    @endphp

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.detail') }} Barang</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('products.index') }}">{{ __('txn.back') }}</a>
            <a class="btn process-btn" href="{{ route('products.mutations', $product) }}">{{ __('ui.stock_mutations_title') }}</a>
            @if($canManageProducts)
                <a class="btn edit-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit_product') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Informasi Barang</h3>
        <div class="row">
            <div class="col-3"><strong>{{ __('ui.code') }}</strong><div>{{ $product->code ?: '-' }}</div></div>
            <div class="col-5"><strong>{{ __('ui.name') }}</strong><div>{{ $product->name }}</div></div>
            <div class="col-4"><strong>{{ __('ui.category') }}</strong><div>{{ $product->category?->name ?: '-' }}</div></div>
            <div class="col-3"><strong>{{ __('ui.unit') }}</strong><div>{{ $product->unit ?: '-' }}</div></div>
            <div class="col-3"><strong>{{ __('ui.stock') }}</strong><div>{{ number_format((int) round((float) $product->stock), 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('ui.price_agent') }}</strong><div>Rp {{ number_format((int) round((float) $product->price_agent), 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('ui.price_sales') }}</strong><div>Rp {{ number_format((int) round((float) $product->price_sales), 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('ui.price_general') }}</strong><div>Rp {{ number_format((int) round((float) $product->price_general), 0, ',', '.') }}</div></div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">Supplier Barang Ini</h3>
        <p class="muted" style="margin-top: 0;">Ringkasan supplier yang pernah memasok barang ini dari data tanda terima barang.</p>
        <div style="overflow-x:auto;">
            <table style="min-width: 960px;">
                <thead>
                <tr>
                    <th>{{ __('txn.supplier') }}</th>
                    <th>{{ __('supplier_stock.total_in') }}</th>
                    <th>Harga Beli Terakhir</th>
                    <th>Tanggal Terakhir Beli</th>
                    <th>Tanda Terima Terakhir</th>
                    <th>{{ __('txn.action') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($supplierRows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['supplier_name'] }}</strong>
                            @if($row['supplier_company_name'] !== '')
                                <div class="muted">{{ $row['supplier_company_name'] }}</div>
                            @endif
                        </td>
                        <td>{{ number_format((int) $row['total_quantity'], 0, ',', '.') }} {{ $row['last_unit'] ?: $product->unit }}</td>
                        <td>Rp {{ number_format((int) $row['last_unit_cost'], 0, ',', '.') }}</td>
                        <td>
                            {{ $row['last_transaction_date'] ? \Illuminate\Support\Carbon::parse($row['last_transaction_date'])->format('d-m-Y') : '-' }}
                        </td>
                        <td>
                            @if((int) $row['last_transaction_id'] > 0 && $row['last_transaction_number'] !== '')
                                <a href="{{ route('outgoing-transactions.show', $row['last_transaction_id']) }}">{{ $row['last_transaction_number'] }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <a class="btn process-soft-btn" href="{{ route('supplier-stock-cards.index', ['supplier_id' => $row['supplier_id'], 'product_id' => $product->id]) }}">
                                Kartu Stok Supplier
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

@extends('layouts.app')

@section('title', __('ui.add_supplier').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.add_supplier') }}</h1>

    <div class="card">
        <h3>{{ __('ui.supplier_profile') }}</h3>
        <p class="muted">{{ __('ui.supplier_profile_note') }}</p>
        <form method="post" action="{{ route('suppliers.store') }}">
            @csrf
            @include('suppliers.partials.form', ['supplier' => $supplier])
            <button type="submit">{{ __('ui.save') }}</button>
            <a class="btn secondary" href="{{ route('suppliers.index') }}">{{ __('ui.cancel') }}</a>
        </form>
    </div>
@endsection

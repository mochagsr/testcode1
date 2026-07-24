@extends('layouts.app')

@section('title', 'Buat SPK - '.config('app.name', 'Laravel'))

@section('content')
@include('produksi.kalender.partials.styles')
<div class="pk-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
        <h1 class="page-title" style="margin:0;">Buat SPK Baru</h1>
        <a class="btn secondary" href="{{ route('produksi.kalender.index') }}">← Kembali ke Kalender</a>
    </div>

    @if($errors->any())
        <div class="pk-panel" style="border-color:#d24b3e; margin-bottom:12px;">
            <b style="color:#d24b3e;">Periksa isian:</b>
            <ul style="margin:6px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li style="font-size:12px;">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pk-panel">
        <form id="pk-spk-form" data-mode="create" method="post" action="{{ route('produksi.spk.store') }}">
            @csrf
            @include('produksi.kalender.partials.form-inner')
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn">Simpan SPK</button>
                <a class="btn secondary" href="{{ route('produksi.kalender.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
    window.PK = {
        lookupUrl: @json(route('produksi.kalender.lookup')),
        data: {},
    };
</script>
@include('produksi.kalender.partials.script')
@endsection

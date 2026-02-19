@extends('layouts.app')

@section('title', 'Wizard Koreksi Transaksi - PgPOS ERP')

@section('content')
    <h1 class="page-title">Wizard Koreksi Transaksi</h1>

    <div class="card">
        <form method="post" action="{{ route('transaction-corrections.store') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="subject_id" value="{{ $subjectId }}">

            <div class="row">
                <div class="col-12">
                    <label>Dokumen</label>
                    <input type="text" value="{{ $subjectLabel }}" disabled>
                </div>
                <div class="col-12">
                    <label>Alasan Koreksi <span class="label-required">*</span></label>
                    <textarea name="reason" rows="3" required>{{ old('reason') }}</textarea>
                </div>
                <div class="col-12">
                    <label>Detail Perubahan Diminta <span class="label-required">*</span></label>
                    <textarea name="requested_changes" rows="8" required placeholder="Contoh: Ubah qty item A dari 10 menjadi 12, tambah item B qty 2">{{ old('requested_changes') }}</textarea>
                </div>
                <div class="col-12 flex">
                    <button type="submit" class="btn">Kirim Permintaan Koreksi</button>
                    <a href="{{ url()->previous() }}" class="btn secondary">Kembali</a>
                </div>
            </div>
        </form>
    </div>
@endsection


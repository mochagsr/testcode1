@extends('layouts.app')

@section('title', $payment->payment_number.' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between;">
        <h1 class="page-title" style="margin:0;">{{ $payment->payment_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('supplier-payables.print-payment', $payment) }}" target="_blank">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('supplier-payables.export-payment-pdf', $payment) }}">{{ __('txn.pdf') }}</a>
        </div>
    </div>

    <div class="card">
        <table>
            <tr><th>{{ __('txn.supplier') }}</th><td>{{ $payment->supplier?->name ?: '-' }}</td></tr>
            <tr><th>{{ __('txn.date') }}</th><td>{{ $payment->payment_date?->format('d-m-Y') }}</td></tr>
            <tr><th>{{ __('supplier_payable.proof_number') }}</th><td>{{ $payment->proof_number ?: '-' }}</td></tr>
            <tr><th>{{ __('txn.amount') }}</th><td>Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('supplier_payable.amount_in_words') }}</th><td>{{ $payment->amount_in_words ?: '-' }}</td></tr>
            <tr>
                <th>{{ __('supplier_payable.payment_proof_photo') }}</th>
                <td>
                    @if($payment->payment_proof_photo_path)
                        <a class="btn secondary id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$payment->payment_proof_photo_path) }}">{{ __('supplier_payable.view_photo') }}</a>
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr><th>{{ __('txn.notes') }}</th><td>{{ $payment->notes ?: '-' }}</td></tr>
        </table>
    </div>

    <div id="id-card-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
        <img id="id-card-modal-image" src="" alt="Payment Proof" style="max-width:25vw; max-height:25vh; width:auto; height:auto; border:2px solid #fff; border-radius:8px; background:#fff;">
    </div>

    <script>
        (function () {
            const modal = document.getElementById('id-card-modal');
            const modalImage = document.getElementById('id-card-modal-image');
            const trigger = document.querySelector('.id-card-preview-trigger');
            if (!modal || !modalImage || !trigger) {
                return;
            }

            function closeModal() {
                modal.style.display = 'none';
                modalImage.setAttribute('src', '');
            }

            trigger.addEventListener('click', (event) => {
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
        })();
    </script>
@endsection

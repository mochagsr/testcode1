<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OutgoingTransaction;
use App\Models\SupplierPayment;
use Illuminate\Contracts\View\View;

class PhotoPrintController extends Controller
{
    public function customerIdCard(Customer $customer): View
    {
        $path = trim((string) $customer->id_card_photo_path);
        abort_if($path === '', 404);

        return view('photos.print', [
            'title' => 'Print Foto KTP Customer',
            'documentLabel' => 'KTP Customer',
            'subjectLabel' => 'Customer',
            'subjectName' => $customer->name ?: '-',
            'referenceLabel' => 'Kota',
            'referenceValue' => $customer->city ?: '-',
            'imageUrl' => asset('storage/'.$path),
        ]);
    }

    public function supplierInvoice(OutgoingTransaction $outgoingTransaction): View
    {
        $path = trim((string) $outgoingTransaction->supplier_invoice_photo_path);
        abort_if($path === '', 404);

        return view('photos.print', [
            'title' => 'Print Foto Nota Supplier',
            'documentLabel' => 'Foto Nota Supplier',
            'subjectLabel' => 'Supplier',
            'subjectName' => $outgoingTransaction->supplier?->name ?: '-',
            'referenceLabel' => 'No. Transaksi',
            'referenceValue' => $outgoingTransaction->transaction_number ?: '-',
            'imageUrl' => asset('storage/'.$path),
        ]);
    }

    public function supplierPaymentProof(SupplierPayment $supplierPayment): View
    {
        $path = trim((string) $supplierPayment->payment_proof_photo_path);
        abort_if($path === '', 404);

        return view('photos.print', [
            'title' => 'Print Bukti Bayar Hutang Supplier',
            'documentLabel' => 'Bukti Bayar Hutang Supplier',
            'subjectLabel' => 'Supplier',
            'subjectName' => $supplierPayment->supplier?->name ?: '-',
            'referenceLabel' => 'No. Bukti',
            'referenceValue' => $supplierPayment->payment_number ?: '-',
            'imageUrl' => asset('storage/'.$path),
        ]);
    }
}

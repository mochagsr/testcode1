<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\SalesInvoice;
use App\Services\ApprovalWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionCorrectionWizardController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService
    ) {}

    public function create(Request $request): View
    {
        $type = trim((string) $request->string('type', 'sales_invoice'));
        $id = $request->integer('id');

        $subject = null;
        $subjectLabel = '-';
        if ($type === 'sales_invoice' && $id > 0) {
            $subject = SalesInvoice::query()->with('customer:id,name')->find($id);
            $subjectLabel = $subject
                ? ((string) $subject->invoice_number.' - '.(string) ($subject->customer?->name ?? '-'))
                : '-';
        }

        return view('transaction_corrections.create', [
            'type' => $type,
            'subjectId' => $id > 0 ? $id : null,
            'subject' => $subject,
            'subjectLabel' => $subjectLabel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:sales_invoice'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1200'],
            'requested_changes' => ['required', 'string', 'max:5000'],
        ]);

        $subject = match ((string) $data['type']) {
            'sales_invoice' => SalesInvoice::query()->findOrFail((int) $data['subject_id']),
            default => null,
        };

        $approval = $this->approvalWorkflowService->create(
            module: 'transaction',
            action: 'correction',
            subject: $subject,
            payload: [
                'type' => (string) $data['type'],
                'requested_changes' => (string) $data['requested_changes'],
            ],
            reason: (string) $data['reason'],
            request: $request
        );

        return redirect()
            ->route('approvals.index')
            ->with('success', 'Permintaan koreksi transaksi berhasil dibuat. Approval #'.$approval->id);
    }
}


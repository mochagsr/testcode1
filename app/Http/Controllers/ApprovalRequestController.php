<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status', 'pending'));
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $requests = ApprovalRequest::query()
            ->with(['requestedBy:id,name', 'approvedBy:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('approval_requests.index', [
            'requests' => $requests,
            'selectedStatus' => $status,
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);
        if ((string) $approvalRequest->status !== 'pending') {
            return back()->withErrors(['approval' => 'Approval request sudah diproses.']);
        }

        $approvedNow = $this->approvalWorkflowService->approve($approvalRequest, $data['approval_note'] ?? null, $request);
        if (! $approvedNow) {
            return back()->withErrors(['approval' => 'Approval request sudah diproses oleh user lain.']);
        }

        return back()->with('success', 'Approval disetujui.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);
        if ((string) $approvalRequest->status !== 'pending') {
            return back()->withErrors(['approval' => 'Approval request sudah diproses.']);
        }

        $rejectedNow = $this->approvalWorkflowService->reject($approvalRequest, $data['approval_note'] ?? null, $request);
        if (! $rejectedNow) {
            return back()->withErrors(['approval' => 'Approval request sudah diproses oleh user lain.']);
        }

        return back()->with('success', 'Approval ditolak.');
    }

    public function reExecute(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        if ((string) $approvalRequest->status !== 'approved') {
            return back()->withErrors(['approval' => 'Hanya approval berstatus approved yang bisa dieksekusi ulang.']);
        }

        $executionSuccess = $this->approvalWorkflowService->reExecute($approvalRequest, $request);
        if ($executionSuccess) {
            return back()->with('success', 'Eksekusi ulang berhasil.');
        }

        return back()->withErrors(['approval' => 'Eksekusi ulang gagal. Silakan cek detail pada kolom eksekusi.']);
    }
}

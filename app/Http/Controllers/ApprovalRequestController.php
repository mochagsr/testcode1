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

        $this->approvalWorkflowService->approve($approvalRequest, $data['approval_note'] ?? null, $request);

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

        $this->approvalWorkflowService->reject($approvalRequest, $data['approval_note'] ?? null, $request);

        return back()->with('success', 'Approval ditolak.');
    }
}


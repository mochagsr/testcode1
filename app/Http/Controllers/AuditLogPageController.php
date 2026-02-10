<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit_logs.index', [
            'logs' => $logs,
            'search' => $search,
        ]);
    }
}

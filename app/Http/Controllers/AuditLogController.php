<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $action = $request->string('action')->toString();

        $logs = AuditLog::query()
            ->with('actor')
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('audit-logs.index', ['logs' => $logs, 'actions' => $actions, 'action' => $action]);
    }
}

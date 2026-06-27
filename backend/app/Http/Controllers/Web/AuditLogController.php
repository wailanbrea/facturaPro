<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->with('user')->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $actions = ActivityLog::query()
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->pluck('total', 'action');

        return view('audit.index', compact('logs', 'actions'));
    }
}

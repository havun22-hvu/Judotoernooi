<?php

namespace App\Http\Controllers;

use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth('organisator')->user();
            if (!$user?->isSitebeheerder()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = SystemAlert::query()->orderByDesc('created_at');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->get('unread')) {
            $query->unread();
        }

        $alerts = $query->paginate(50);

        return view('admin.alerts', compact('alerts'));
    }

    public function markRead(SystemAlert $alert)
    {
        $alert->update(['is_read' => true]);

        return back()->with('success', 'Alert gemarkeerd als gelezen.');
    }

    public function markAllRead()
    {
        SystemAlert::unread()->update(['is_read' => true]);

        return back()->with('success', 'Alle alerts gemarkeerd als gelezen.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    /**
     * Display all system alerts (newest first).
     */
    public function index(Request $request)
    {
        $user = auth('organisator')->user();
        if (!$user?->isSitebeheerder()) {
            abort(403);
        }

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

    /**
     * Mark a single alert as read.
     */
    public function markRead(SystemAlert $alert)
    {
        $user = auth('organisator')->user();
        if (!$user?->isSitebeheerder()) {
            abort(403);
        }

        $alert->update(['is_read' => true]);

        return back()->with('success', 'Alert gemarkeerd als gelezen.');
    }

    /**
     * Mark all alerts as read.
     */
    public function markAllRead()
    {
        $user = auth('organisator')->user();
        if (!$user?->isSitebeheerder()) {
            abort(403);
        }

        SystemAlert::unread()->update(['is_read' => true]);

        return back()->with('success', 'Alle alerts gemarkeerd als gelezen.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SystemAlert;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    /**
     * Check if user is sitebeheerder.
     */
    private function checkSitebeheerder(): void
    {
        $user = auth('organisator')->user();
        if (!$user?->isSitebeheerder()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->checkSitebeheerder();
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
        $this->checkSitebeheerder();
        $alert->update(['is_read' => true]);

        return back()->with('success', 'Alert gemarkeerd als gelezen.');
    }

    public function markAllRead()
    {
        $this->checkSitebeheerder();
        SystemAlert::unread()->update(['is_read' => true]);

        return back()->with('success', 'Alle alerts gemarkeerd als gelezen.');
    }
}

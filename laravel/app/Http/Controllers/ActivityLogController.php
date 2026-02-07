<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Organisator $organisator, Toernooi $toernooi, Request $request): View
    {
        $query = $toernooi->activityLogs()->latest();

        // Filter on action
        if ($request->filled('actie')) {
            $query->where('actie', $request->actie);
        }

        // Filter on model type
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Search in description
        if ($request->filled('zoek')) {
            $query->where('beschrijving', 'like', '%' . $request->zoek . '%');
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get distinct actions and model types for filter dropdowns
        $acties = $toernooi->activityLogs()
            ->select('actie')
            ->distinct()
            ->orderBy('actie')
            ->pluck('actie');

        $modelTypes = $toernooi->activityLogs()
            ->select('model_type')
            ->whereNotNull('model_type')
            ->distinct()
            ->orderBy('model_type')
            ->pluck('model_type');

        return view('pages.toernooi.activiteiten', compact('toernooi', 'logs', 'acties', 'modelTypes'));
    }
}

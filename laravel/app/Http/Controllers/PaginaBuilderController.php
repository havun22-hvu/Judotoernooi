<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaginaBuilderController extends Controller
{
    public function index(Toernooi $toernooi): View
    {
        $blokken = $toernooi->pagina_content['blokken'] ?? [];

        return view('pages.toernooi.pagina-builder', [
            'toernooi' => $toernooi,
            'blokken' => $blokken,
        ]);
    }

    public function opslaan(Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate([
            'blokken' => 'nullable|array',
            'blokken.*.id' => 'required|string',
            'blokken.*.type' => 'required|string|in:header,tekst,afbeelding,sponsors,video,info_kaart',
            'blokken.*.order' => 'required|integer',
            'blokken.*.data' => 'required|array',
        ]);

        $toernooi->update([
            'pagina_content' => ['blokken' => $request->input('blokken', [])],
        ]);

        return response()->json(['success' => true]);
    }

    public function upload(Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate([
            'afbeelding' => 'required|image|max:5120', // Max 5MB
        ]);

        $path = $request->file('afbeelding')
            ->store("pagina-afbeeldingen/{$toernooi->id}", 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }

    public function verwijderAfbeelding(Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        // Security: ensure path is within toernooi's folder
        if (!Str::startsWith($path, "pagina-afbeeldingen/{$toernooi->id}/")) {
            return response()->json(['success' => false, 'error' => 'Invalid path'], 403);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true]);
    }
}

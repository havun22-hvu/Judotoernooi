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
        // Check for new Pro format (sections) or legacy format (blokken)
        $sections = $toernooi->pagina_content['sections'] ?? null;

        // If we have sections, use Pro builder
        if ($sections !== null) {
            return view('pages.toernooi.pagina-builder-pro', [
                'toernooi' => $toernooi,
                'sections' => $sections,
            ]);
        }

        // Legacy fallback - redirect to Pro with migration
        $blokken = $toernooi->pagina_content['blokken'] ?? [];

        // Auto-migrate old blokken to new sections format
        if (!empty($blokken)) {
            $sections = $this->migrateBlokkenToSections($blokken);
            $toernooi->update([
                'pagina_content' => [
                    'sections' => $sections,
                    'blokken' => $blokken, // Keep legacy for backup
                ],
            ]);

            return view('pages.toernooi.pagina-builder-pro', [
                'toernooi' => $toernooi,
                'sections' => $sections,
            ]);
        }

        // Empty state - start with Pro builder
        return view('pages.toernooi.pagina-builder-pro', [
            'toernooi' => $toernooi,
            'sections' => [],
        ]);
    }

    protected function migrateBlokkenToSections(array $blokken): array
    {
        $sections = [];

        foreach ($blokken as $blok) {
            // Map old block types to new
            $typeMap = [
                'header' => 'hero',
                'tekst' => 'text',
                'afbeelding' => 'image',
                'sponsors' => 'sponsors',
                'video' => 'video',
                'info_kaart' => 'info_card',
            ];

            $newType = $typeMap[$blok['type']] ?? $blok['type'];

            // Convert data format
            $newData = $this->convertBlockData($blok['type'], $blok['data'] ?? []);

            $sections[] = [
                'id' => $blok['id'] ?? uniqid('section-'),
                'layout' => 'full',
                'columns' => [[
                    'blocks' => [[
                        'id' => uniqid('block-'),
                        'type' => $newType,
                        'data' => $newData,
                    ]],
                ]],
                'settings' => [
                    'bgColor' => '#ffffff',
                    'padding' => 'py-12 px-6',
                    'textColor' => '#1f2937',
                ],
            ];
        }

        return $sections;
    }

    protected function convertBlockData(string $oldType, array $data): array
    {
        switch ($oldType) {
            case 'header':
                return [
                    'title' => $data['titel'] ?? '',
                    'subtitle' => $data['subtitel'] ?? '',
                    'bgImage' => $data['logo'] ?? null,
                    'buttons' => [['text' => 'Meer info', 'url' => '#', 'style' => 'primary']],
                    'overlay' => true,
                ];
            case 'tekst':
                return ['html' => $data['html'] ?? ''];
            case 'afbeelding':
                return [
                    'src' => $data['src'] ?? null,
                    'alt' => $data['alt'] ?? '',
                    'caption' => $data['caption'] ?? '',
                    'width' => '100%',
                ];
            default:
                return $data;
        }
    }

    public function opslaan(Request $request, Toernooi $toernooi): JsonResponse
    {
        // Support both old and new format
        $sections = $request->input('sections');
        $blokken = $request->input('blokken');
        $themeColor = $request->input('themeColor');

        $content = $toernooi->pagina_content ?? [];

        if ($sections !== null) {
            $content['sections'] = $sections;
        }

        if ($blokken !== null) {
            $content['blokken'] = $blokken;
        }

        if ($themeColor) {
            $toernooi->thema_kleur = $themeColor;
        }

        $toernooi->pagina_content = $content;
        $toernooi->save();

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

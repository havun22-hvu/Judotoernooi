@php
    $isStandalone = request()->routeIs('rol.*') || request()->routeIs('mat.show') || isset($toegang);
@endphp
@if($isStandalone)
{{-- Standalone layout for role-based and device-bound access --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e40af">
    <title>Mat {{ $mat->nummer }} - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-blue-800 text-white px-4 py-3 shadow-lg">
        <h1 class="text-lg font-bold">🥋 Mat {{ $mat->nummer }}</h1>
        <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
    </header>
    <main class="p-4 max-w-4xl mx-auto">
@else
@extends('layouts.app')

@section('title', 'Mat ' . $mat->nummer)

@section('content')
@endif
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Mat {{ $mat->nummer }} - Blok {{ $blok?->nummer ?? '?' }}</h1>
</div>

@forelse($schema as $pouleSchema)
<div class="bg-white rounded-lg shadow mb-6">
    <div class="bg-blue-800 text-white px-6 py-3 rounded-t-lg">
        <h2 class="text-lg font-bold">{{ $pouleSchema['titel'] }}</h2>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-bold mb-2">Judoka's</h3>
                @foreach($pouleSchema['judokas'] as $judoka)
                <div class="py-1">
                    {{ $judoka['naam'] }}
                    <span class="text-gray-500 text-sm">({{ $judoka['club'] ?? '-' }})</span>
                </div>
                @endforeach
            </div>

            <div>
                <h3 class="font-bold mb-2">Wedstrijden</h3>
                @foreach($pouleSchema['wedstrijden'] as $w)
                <div class="py-1 border-b last:border-0 flex justify-between items-center">
                    <span>
                        {{ $w['volgorde'] }}.
                        <span class="text-blue-600">{{ $w['wit']['naam'] }}</span>
                        vs
                        <span class="text-red-600">{{ $w['blauw']['naam'] }}</span>
                    </span>
                    @if($w['is_gespeeld'])
                    <span class="text-green-600">✓</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Geen poules op deze mat in dit blok
</div>
@endforelse

@if($isStandalone)
@if(isset($toegang))
<a href="{{ route('mat.interface', ['organisator' => $toernooi->organisator->slug, 'toernooi' => $toernooi->slug, 'toegang' => $toegang->id]) }}" class="text-blue-600 hover:text-blue-800">
    ← Terug naar matten
</a>
@else
<a href="{{ route('rol.mat') }}" class="text-blue-600 hover:text-blue-800">
    ← Terug naar matten
</a>
@endif
    </main>
</body>
</html>
@else
<a href="{{ route('toernooi.mat.index', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
    ← Terug naar matten
</a>
@endsection
@endif

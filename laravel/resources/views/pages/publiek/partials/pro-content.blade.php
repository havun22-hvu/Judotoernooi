{{-- Orchestrator: renders header + sections + footer from Pro builder data --}}
@php
    $paginaContent = $toernooi->pagina_content ?? [];
    $header = $paginaContent['header'] ?? null;
    $footer = $paginaContent['footer'] ?? null;
    $sections = $paginaContent['sections'] ?? [];
@endphp

{{-- Header --}}
@if($header && !empty($header['columns'][0]['blocks'] ?? []))
    @include('pages.publiek.partials.pro-header-footer', ['hfSection' => $header])
@endif

{{-- Sections --}}
@foreach($sections as $section)
    @include('pages.publiek.partials.pro-section', ['section' => $section])
@endforeach

{{-- Footer --}}
@if($footer && !empty($footer['columns'][0]['blocks'] ?? []))
    @include('pages.publiek.partials.pro-header-footer', ['hfSection' => $footer])
@endif

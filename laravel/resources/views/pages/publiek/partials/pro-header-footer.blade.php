{{-- Renders header or footer section (single column with blocks) --}}
@php
    $settings = $hfSection['settings'] ?? [];
    $padding = $settings['padding'] ?? 'py-4 px-6';

    $style = '';
    if (!empty($settings['bgColor'])) {
        $style .= 'background-color: ' . e($settings['bgColor']) . ';';
    }
    if (!empty($settings['bgImage'])) {
        $style .= "background-image: url('" . asset('storage/' . $settings['bgImage']) . "'); background-size: cover; background-position: center;";
    }
    if (!empty($settings['textColor'])) {
        $style .= 'color: ' . e($settings['textColor']) . ';';
    }
@endphp
<div style="{{ $style }}" class="{{ $padding }}">
    <div class="space-y-4">
        @foreach($hfSection['columns'][0]['blocks'] ?? [] as $block)
            @if(view()->exists('pages.publiek.partials.blocks.' . ($block['type'] ?? '')))
                @include('pages.publiek.partials.blocks.' . $block['type'], ['block' => $block])
            @endif
        @endforeach
    </div>
</div>

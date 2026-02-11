@php
    $level = $block['data']['level'] ?? 'h2';
    $text = e($block['data']['text'] ?? '');
    $align = $block['data']['align'] ?? 'left';
    $sizes = ['h1' => '2.5rem', 'h2' => '2rem', 'h3' => '1.5rem', 'h4' => '1.25rem'];
    $fontSize = $sizes[$level] ?? '1.5rem';
@endphp
<{{ $level }} class="font-bold" style="text-align: {{ $align }}; font-size: {{ $fontSize }}">{{ $text }}</{{ $level }}>

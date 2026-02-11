@php
    $style = $block['data']['style'] ?? 'primary';
    $align = $block['data']['align'] ?? 'left';
    $url = $block['data']['url'] ?? '#';
    $text = e($block['data']['text'] ?? 'Button');
    $classes = match($style) {
        'primary' => 'bg-blue-600 text-white',
        'secondary' => 'bg-gray-200 text-gray-800',
        'outline' => 'border-2 border-current',
        default => 'bg-blue-600 text-white',
    };
@endphp
<div style="text-align: {{ $align }}">
    <a href="{{ $url }}" class="inline-block px-6 py-3 rounded-lg font-medium {{ $classes }}">{{ $text }}</a>
</div>

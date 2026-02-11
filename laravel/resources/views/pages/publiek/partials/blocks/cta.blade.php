<div class="rounded-xl p-8 text-center text-white" style="background-color: {{ $block['data']['bgColor'] ?? '#2563eb' }}">
    @if(!empty($block['data']['title']))
        <h2 class="text-2xl font-bold mb-2">{{ $block['data']['title'] }}</h2>
    @endif
    @if(!empty($block['data']['text']))
        <p class="opacity-90 mb-6">{{ $block['data']['text'] }}</p>
    @endif
    <a href="{{ $block['data']['buttonUrl'] ?? '#' }}" class="inline-block px-8 py-3 bg-white text-gray-800 rounded-lg font-medium">
        {{ $block['data']['buttonText'] ?? 'Klik hier' }}
    </a>
</div>

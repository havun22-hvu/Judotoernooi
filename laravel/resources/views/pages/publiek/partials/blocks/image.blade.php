@if(!empty($block['data']['src']))
<figure style="width: {{ $block['data']['width'] ?? '100%' }}">
    <img src="{{ asset('storage/' . $block['data']['src']) }}" alt="{{ e($block['data']['alt'] ?? '') }}" class="rounded-lg w-full">
    @if(!empty($block['data']['caption']))
        <figcaption class="text-sm text-gray-500 mt-2">{{ $block['data']['caption'] }}</figcaption>
    @endif
</figure>
@endif

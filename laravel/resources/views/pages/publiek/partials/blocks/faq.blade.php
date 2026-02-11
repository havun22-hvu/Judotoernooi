@php $items = $block['data']['items'] ?? []; @endphp
<div>
    @if(!empty($block['data']['title']))
        <h2 class="text-2xl font-bold text-center mb-8">{{ e($block['data']['title']) }}</h2>
    @endif
    @if(!empty($items))
    <div class="space-y-4 max-w-2xl mx-auto">
        @foreach($items as $item)
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold mb-2">{{ e($item['question'] ?? '') }}</h3>
                <p class="text-gray-600">{{ e($item['answer'] ?? '') }}</p>
            </div>
        @endforeach
    </div>
    @endif
</div>

@php $items = $block['data']['items'] ?? []; @endphp
<div>
    @if(!empty($block['data']['title']))
        <h2 class="text-2xl font-bold text-center mb-8">{{ $block['data']['title'] }}</h2>
    @endif
    @if(!empty($items))
    <div class="space-y-4">
        @foreach($items as $item)
            <div class="flex gap-4 items-start">
                <div class="w-20 flex-shrink-0 text-right">
                    <span class="font-bold text-blue-600">{{ $item['time'] ?? '' }}</span>
                </div>
                <div class="w-3 h-3 bg-blue-600 rounded-full mt-1.5 flex-shrink-0"></div>
                <div class="flex-1 pb-4 border-l-2 border-gray-200 pl-4 -ml-1.5">
                    <h3 class="font-semibold">{{ $item['title'] ?? '' }}</h3>
                    <p class="text-gray-600 text-sm">{{ $item['description'] ?? '' }}</p>
                </div>
            </div>
        @endforeach
    </div>
    @endif
</div>

@php $features = $block['data']['features'] ?? []; @endphp
<div>
    @if(!empty($block['data']['title']))
        <h2 class="text-2xl font-bold text-center mb-8">{{ $block['data']['title'] }}</h2>
    @endif
    @if(!empty($features))
    <div class="grid md:grid-cols-3 gap-6">
        @foreach($features as $feature)
            <div class="flex gap-4">
                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold mb-1">{{ $feature['title'] ?? '' }}</h3>
                    <p class="text-gray-600 text-sm">{{ $feature['description'] ?? '' }}</p>
                </div>
            </div>
        @endforeach
    </div>
    @endif
</div>

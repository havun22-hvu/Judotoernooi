@php $cards = $block['data']['cards'] ?? []; @endphp
@if(!empty($cards))
<div class="grid md:grid-cols-3 gap-6">
    @foreach($cards as $card)
        <div class="bg-white rounded-xl shadow p-6 text-center">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-2">{{ e($card['title'] ?? '') }}</h3>
            <p class="text-gray-600">{{ e($card['text'] ?? '') }}</p>
        </div>
    @endforeach
</div>
@endif

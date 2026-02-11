<div class="bg-gray-50 rounded-xl p-6">
    <h3 class="font-semibold mb-4">Contact</h3>
    <div class="space-y-2">
        @if(!empty($block['data']['email']))
            <p><a href="mailto:{{ $block['data']['email'] }}" class="text-blue-600 hover:underline">{{ $block['data']['email'] }}</a></p>
        @endif
        @if(!empty($block['data']['phone']))
            <p><a href="tel:{{ $block['data']['phone'] }}" class="text-blue-600 hover:underline">{{ $block['data']['phone'] }}</a></p>
        @endif
    </div>
</div>

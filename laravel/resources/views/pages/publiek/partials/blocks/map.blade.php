@if(!empty($block['data']['address']))
<div class="rounded-lg overflow-hidden" style="height: {{ $block['data']['height'] ?? '300px' }}">
    <iframe
        src="https://maps.google.com/maps?q={{ urlencode($block['data']['address']) }}&output=embed"
        class="w-full h-full border-0"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
    </iframe>
</div>
@endif

@php $sponsors = $block['data']['sponsors'] ?? []; @endphp
@if(!empty($sponsors))
<div class="text-center">
    <h3 class="text-xl font-semibold mb-6">Onze Sponsors</h3>
    <div class="flex flex-wrap justify-center gap-8 items-center">
        @foreach($sponsors as $sponsor)
            @if(!empty($sponsor['logo']))
                @if(!empty($sponsor['url']))
                    <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener" class="hover:opacity-80 transition-opacity">
                        <img src="{{ asset('storage/' . $sponsor['logo']) }}" alt="{{ $sponsor['naam'] ?? 'Sponsor' }}" class="h-16 object-contain">
                    </a>
                @else
                    <img src="{{ asset('storage/' . $sponsor['logo']) }}" alt="{{ $sponsor['naam'] ?? 'Sponsor' }}" class="h-16 object-contain">
                @endif
            @endif
        @endforeach
    </div>
</div>
@endif

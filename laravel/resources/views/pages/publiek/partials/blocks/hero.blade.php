@php
    $bgImage = $block['data']['bgImage'] ?? null;
    $overlay = $block['data']['overlay'] ?? false;
    $title = $block['data']['title'] ?? '';
    $subtitle = $block['data']['subtitle'] ?? '';
    $buttons = $block['data']['buttons'] ?? [];
@endphp
<div class="relative rounded-xl overflow-hidden {{ $bgImage ? '' : 'bg-gradient-to-r from-blue-600 to-blue-800' }}"
     @if($bgImage) style="background-image: url('{{ asset('storage/' . $bgImage) }}'); background-size: cover; background-position: center;" @endif>
    @if($overlay)
        <div class="absolute inset-0 bg-black/50"></div>
    @endif
    <div class="relative z-10 py-20 px-8 text-center text-white">
        @if($title)
            <h1 class="text-4xl md:text-5xl font-bold mb-4">{{ $title }}</h1>
        @endif
        @if($subtitle)
            <p class="text-xl md:text-2xl opacity-90 mb-8">{{ $subtitle }}</p>
        @endif
        @if(!empty($buttons))
            <div class="flex gap-4 justify-center flex-wrap">
                @foreach($buttons as $btn)
                    <a href="{{ $btn['url'] ?? '#' }}"
                       class="px-8 py-3 rounded-lg font-medium {{ ($btn['style'] ?? 'primary') === 'primary' ? 'bg-white text-blue-600' : 'border-2 border-white text-white' }}">
                        {{ $btn['text'] ?? '' }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

@if(!empty($block['data']['url']))
@php
    $videoUrl = $block['data']['url'];
    $embedUrl = '';
    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $videoUrl, $matches)) {
        $embedUrl = 'https://www.youtube.com/embed/' . $matches[1];
    } elseif (preg_match('/youtu\.be\/([^?]+)/', $videoUrl, $matches)) {
        $embedUrl = 'https://www.youtube.com/embed/' . $matches[1];
    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches)) {
        $embedUrl = 'https://player.vimeo.com/video/' . $matches[1];
    }
@endphp
@if($embedUrl)
<div class="aspect-video bg-gray-900 rounded-lg">
    <iframe src="{{ $embedUrl }}" class="w-full h-full rounded-lg" frameborder="0" allowfullscreen loading="lazy"></iframe>
</div>
@endif
@endif

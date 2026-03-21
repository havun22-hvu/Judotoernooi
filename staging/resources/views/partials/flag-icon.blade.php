{{-- Inline SVG flag icon. Usage: @include('partials.flag-icon', ['lang' => 'nl']) --}}
@if($lang === 'nl')
<svg class="inline-block w-5 h-4 rounded-sm" viewBox="0 0 20 15" xmlns="http://www.w3.org/2000/svg">
    <rect width="20" height="5" fill="#AE1C28"/>
    <rect width="20" height="5" y="5" fill="#FFF"/>
    <rect width="20" height="5" y="10" fill="#21468B"/>
</svg>
@elseif($lang === 'en')
<svg class="inline-block w-5 h-4 rounded-sm" viewBox="0 0 20 15" xmlns="http://www.w3.org/2000/svg">
    <rect width="20" height="15" fill="#012169"/>
    <path d="M0 0L20 15M20 0L0 15" stroke="#FFF" stroke-width="2.5"/>
    <path d="M0 0L20 15M20 0L0 15" stroke="#C8102E" stroke-width="1.5"/>
    <path d="M10 0V15M0 7.5H20" stroke="#FFF" stroke-width="4"/>
    <path d="M10 0V15M0 7.5H20" stroke="#C8102E" stroke-width="2.5"/>
</svg>
@endif

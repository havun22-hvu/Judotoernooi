{{-- Compact locale switcher for coach portal --}}
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" @click.away="open = false" class="text-gray-500 hover:text-gray-700 text-sm focus:outline-none">
        @include('partials.flag-icon', ['lang' => app()->getLocale()])
    </button>
    <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50">
        <form action="{{ route('locale.switch', 'nl') }}" method="POST">
            @csrf
            <input type="hidden" name="club_id" value="{{ $club->id }}">
            <input type="hidden" name="toernooi_id" value="{{ $toernooi->id }}">
            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
            </button>
        </form>
        <form action="{{ route('locale.switch', 'en') }}" method="POST">
            @csrf
            <input type="hidden" name="club_id" value="{{ $club->id }}">
            <input type="hidden" name="toernooi_id" value="{{ $toernooi->id }}">
            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                @include('partials.flag-icon', ['lang' => 'en']) English
            </button>
        </form>
    </div>
</div>

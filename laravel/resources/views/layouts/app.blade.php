<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ isset($toernooi) ? $toernooi->naam : 'Judo Toernooi' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-800 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="{{ route('dashboard') }}" class="text-xl font-bold">{{ isset($toernooi) ? $toernooi->naam : 'Judo Toernooi' }}</a>
                    @if(isset($toernooi))
                    <div class="hidden md:flex space-x-4">
                        <a href="{{ route('toernooi.judoka.index', $toernooi) }}" class="hover:text-blue-200">Judoka's</a>
                        <a href="{{ route('toernooi.poule.index', $toernooi) }}" class="hover:text-blue-200">Poules</a>
                        <a href="{{ route('toernooi.blok.index', $toernooi) }}" class="hover:text-blue-200">Blokken</a>
                        <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="hover:text-blue-200">Zaaloverzicht</a>
                        <a href="{{ route('toernooi.weging.interface', $toernooi) }}" class="hover:text-blue-200">Weging</a>
                        <a href="{{ route('toernooi.mat.interface', $toernooi) }}" class="hover:text-blue-200">Matten</a>
                    </div>
                    @endif
                </div>
                <div class="flex items-center space-x-4">
                    @if(isset($toernooi) && session("toernooi_{$toernooi->id}_rol"))
                    @php $rol = session("toernooi_{$toernooi->id}_rol"); @endphp
                    <span class="text-blue-200 text-sm">
                        @switch($rol)
                            @case('admin') 👑 Admin @break
                            @case('jury') ⚖️ Jury @break
                            @case('weging') ⚖️ Weging @break
                            @case('mat') 🥋 Mat {{ session("toernooi_{$toernooi->id}_mat") }} @break
                            @case('spreker') 🎙️ Spreker @break
                        @endswitch
                    </span>
                    <form action="{{ route('toernooi.auth.logout', $toernooi) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-blue-200 hover:text-white text-sm">Uitloggen</button>
                    </form>
                    @else
                    @if(isset($toernooi))
                    <a href="{{ route('toernooi.auth.login', $toernooi) }}" class="text-blue-200 hover:text-white text-sm">Inloggen</a>
                    @endif
                    @endif
                    <a href="{{ route('toernooi.index') }}" class="hover:text-blue-200">Toernooien</a>
                </div>
            </div>
        </div>
    </nav>

    @if(session('success'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    </div>
    @endif

    <main class="max-w-7xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm">
            &copy; {{ date('Y') }} Havun - Judo Toernooi Management Systeem
        </div>
    </footer>
</body>
</html>

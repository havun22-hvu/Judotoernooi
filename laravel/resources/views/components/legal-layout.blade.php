<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'JudoToernooi') }}</title>

        <!-- Canonical URL (SEO) -->
        <link rel="canonical" href="https://judotournament.org{{ request()->getPathInfo() }}">

        <!-- Favicons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <a href="/" class="flex items-center text-xl font-bold text-blue-600">
                            JudoToernooi
                        </a>
                        @auth('organisator')
                            <a href="{{ route('admin.index') }}" class="text-sm text-blue-600 hover:text-blue-700">
                                → Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-700">
                                → Inloggen
                            </a>
                        @endauth
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-grow">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="bg-gray-800 text-white py-4 mt-auto shrink-0">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 text-xs text-gray-300 mb-2">
                        <a href="{{ route('legal.terms') }}" class="hover:text-white">Voorwaarden</a>
                        <span class="text-gray-500">•</span>
                        <a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a>
                        <span class="text-gray-500">•</span>
                        <a href="{{ route('legal.cookies') }}" class="hover:text-white">Cookies</a>
                        <span class="text-gray-500">•</span>
                        <a href="mailto:havun22@gmail.com" class="hover:text-white">Contact</a>
                    </div>
                    <div class="text-center text-xs text-gray-400">
                        &copy; {{ date('Y') }} Havun
                        <span class="mx-1">•</span>
                        KvK 98516000
                        <span class="mx-1">•</span>
                        BTW-vrij (KOR)
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>

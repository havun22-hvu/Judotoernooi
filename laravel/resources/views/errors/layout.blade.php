<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - Judo Toernooi</title>
    <x-seo :noindex="true" />
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="text-6xl font-bold text-gray-300 mb-4">@yield('code')</div>
        <h1 class="text-xl font-semibold text-gray-800 mb-2">@yield('heading')</h1>
        <p class="text-gray-600 mb-6">@yield('message')</p>

        <div class="space-y-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}"
               class="inline-block w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                Ga terug
            </a>

            <button onclick="meldProbleem()" id="meld-btn"
                    class="w-full px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition text-sm">
                Meld dit probleem
            </button>
            <div id="meld-status" class="text-sm hidden"></div>
        </div>
    </div>

    <script>
        function meldProbleem() {
            const btn = document.getElementById('meld-btn');
            const status = document.getElementById('meld-status');
            btn.disabled = true;
            btn.textContent = 'Verzenden...';

            fetch('/error-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    url: window.location.href,
                    referrer: document.referrer,
                    error_code: '@yield('code')',
                    user_agent: navigator.userAgent,
                }),
            })
            .then(r => r.json())
            .then(data => {
                status.classList.remove('hidden');
                status.className = 'text-sm text-green-600 mt-2';
                status.textContent = 'Bedankt! Het probleem is gemeld aan Havun.';
                btn.classList.add('hidden');
            })
            .catch(() => {
                status.classList.remove('hidden');
                status.className = 'text-sm text-red-600 mt-2';
                status.textContent = 'Kon melding niet versturen. Neem contact op met de organisator.';
                btn.disabled = false;
                btn.textContent = 'Meld dit probleem';
            });
        }
    </script>
</body>
</html>

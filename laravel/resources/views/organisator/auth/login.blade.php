<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Inloggen') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        {{-- Language switcher --}}
        <div class="flex justify-end mb-2" x-data="toggle">
            <div class="relative">
                <button @click="toggle" @click.away="close" class="flex items-center text-gray-500 hover:text-gray-700 text-sm focus:outline-none">
                    @include('partials.flag-icon', ['lang' => app()->getLocale()])
                    <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50 border">
                    <form action="{{ route('locale.switch', 'nl') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                            @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
                        </button>
                    </form>
                    <form action="{{ route('locale.switch', 'en') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                            @include('partials.flag-icon', ['lang' => 'en']) English
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
        @endif

        @if(session('status'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
            {{ session('status') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            {{ session('warning') }}
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        @if($errors->has('token'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ $errors->first('token') }}
        </div>
        @endif

        {{-- Pill Tabs --}}
        <div class="flex mb-6 bg-gray-100 rounded-lg p-1">
            <button type="button" id="tab-login" onclick="switchTab('login')"
                class="flex-1 py-2.5 text-sm font-semibold rounded-md transition-all bg-white text-gray-800 shadow">
                {{ __('Inloggen') }}
            </button>
            <button type="button" id="tab-register" onclick="switchTab('register')"
                class="flex-1 py-2.5 text-sm font-semibold rounded-md transition-all text-gray-500">
                {{ __('Registreren') }}
            </button>
        </div>

        {{-- ============ LOGIN TAB ============ --}}
        <div id="login-tab">
            <form action="{{ route('login.submit') }}" method="POST" id="loginForm">
                @csrf
                <input type="hidden" name="fingerprint" id="fingerprint-input">

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('E-mailadres') }}
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                    @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Wachtwoord') }}
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 pr-10 focus:border-blue-500 focus:ring-blue-500">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
                            <svg id="eye-open" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="eye-closed" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-600">{{ __('Onthoud mij') }}</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-800">
                        {{ __('Wachtwoord vergeten?') }}
                    </a>
                </div>

                {{-- DO NOT REMOVE: Login submit button --}}
                <button type="submit" id="login-btn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    {{ __('Inloggen') }}
                </button>
            </form>

            {{-- Divider --}}
            <div class="flex items-center my-6">
                <div class="flex-1 border-t border-gray-300"></div>
                <span class="px-4 text-sm text-gray-500">{{ __('of') }}</span>
                <div class="flex-1 border-t border-gray-300"></div>
            </div>

            {{-- Alternative login methods --}}
            <div id="alt-login-methods">
                {{-- QR button (desktop only) --}}
                <button type="button" id="qr-login-btn" onclick="toggleQrModal()"
                    class="hidden w-full py-3 px-4 border-2 border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    {{ __('Inloggen met QR code') }}
                </button>

                {{-- Biometric button (smartphone only) --}}
                <button type="button" id="biometric-login-btn" onclick="startBiometric()"
                    class="hidden w-full py-3 px-4 border-2 border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                    </svg>
                    {{ __('Inloggen met biometrie') }}
                </button>
            </div>

            {{-- Error message for alt methods --}}
            <p id="login-error" class="text-red-600 text-sm text-center hidden mt-3"></p>
        </div>

        {{-- ============ REGISTER TAB ============ --}}
        <div id="register-tab" class="hidden">
            <form action="{{ route('register.submit') }}" method="POST" id="registerForm">
                @csrf

                <div class="mb-4">
                    <label for="reg-organisatie" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Naam judoschool / organisatie') }}
                    </label>
                    <input type="text"
                           id="reg-organisatie"
                           name="organisatie_naam"
                           value="{{ old('organisatie_naam') }}"
                           required
                           placeholder="{{ __('Naam van uw judoschool') }}"
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('organisatie_naam') border-red-500 @enderror">
                    @error('organisatie_naam')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="reg-naam" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Uw naam') }} <span class="text-gray-400">({{ __('contactpersoon') }})</span>
                    </label>
                    <input type="text"
                           id="reg-naam"
                           name="naam"
                           value="{{ old('naam') }}"
                           required
                           placeholder="{{ __('bijv. Jan Jansen') }}"
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('naam') border-red-500 @enderror">
                    @error('naam')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('E-mailadres') }}
                    </label>
                    <input type="email"
                           id="reg-email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                    @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="reg-telefoon" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Telefoonnummer') }} <span class="text-gray-400">({{ __('optioneel') }})</span>
                    </label>
                    <input type="tel"
                           id="reg-telefoon"
                           name="telefoon"
                           value="{{ old('telefoon') }}"
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500">
                </div>

                {{-- DO NOT REMOVE: Registration submit button --}}
                <button type="submit" id="register-btn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    {{ __('Registratielink versturen') }}
                </button>

                <p class="text-xs text-gray-500 text-center mt-3">
                    {{ __('We sturen een activatielink naar je e-mailadres.') }}
                </p>
            </form>
        </div>
    </div>

    {{-- QR Login Modal --}}
    <div id="qr-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50" onclick="if(event.target === this) toggleQrModal()">
        <div class="bg-white rounded-2xl p-6 m-4 max-w-sm w-full shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-800">{{ __('Scan met je telefoon') }}</h3>
                <button onclick="toggleQrModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="text-center">
                <div id="qr-container" class="w-48 h-48 mx-auto bg-gray-50 rounded-lg flex items-center justify-center mb-4 border">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                </div>
                <p class="text-sm text-gray-500 mb-2">{{ __('Scan met je telefoon waarop je al bent ingelogd') }}</p>
                <p id="qr-status" class="text-xs text-gray-400">{{ __('QR code laden...') }}</p>
                <p id="qr-timer" class="text-xs text-blue-500 hidden mt-1">{{ __('Verloopt over') }} <span id="timer">5:00</span></p>
            </div>
        </div>
    </div>

<script @nonce>
const __t = {
    somethingWrong: @json(__('Er ging iets mis')),
    biometricNotSupported: @json(__('Biometrie niet ondersteund op dit apparaat')),
    noPasskeyFound: @json(__('Geen passkey gevonden. Log eerst in met wachtwoord.')),
    biometricFailed: @json(__('Biometrie mislukt. Gebruik je wachtwoord.')),
    qrLoading: @json(__('QR code laden...')),
    scanWithPhone: @json(__('Scan met je telefoon')),
    qrLoadFailed: @json(__('QR laden mislukt')),
    approved: @json(__('Goedgekeurd! Doorsturen...')),
    refreshQr: @json(__('Vernieuw QR')),
    qrExpired: @json(__('QR code verlopen')),
    registering: @json(__('Bezig met versturen...')),
};

// Tab switching
function switchTab(tab) {
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const tabLogin = document.getElementById('tab-login');
    const tabRegister = document.getElementById('tab-register');

    if (tab === 'login') {
        loginTab.classList.remove('hidden');
        registerTab.classList.add('hidden');
        tabLogin.classList.add('bg-white', 'text-gray-800', 'shadow');
        tabLogin.classList.remove('text-gray-500');
        tabRegister.classList.remove('bg-white', 'text-gray-800', 'shadow');
        tabRegister.classList.add('text-gray-500');
    } else {
        loginTab.classList.add('hidden');
        registerTab.classList.remove('hidden');
        tabRegister.classList.add('bg-white', 'text-gray-800', 'shadow');
        tabRegister.classList.remove('text-gray-500');
        tabLogin.classList.remove('bg-white', 'text-gray-800', 'shadow');
        tabLogin.classList.add('text-gray-500');
    }
}

// Password toggle
function togglePassword() {
    const input = document.getElementById('password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');
    if (input.type === 'password') {
        input.type = 'text'; eyeOpen.classList.remove('hidden'); eyeClosed.classList.add('hidden');
    } else {
        input.type = 'password'; eyeOpen.classList.add('hidden'); eyeClosed.classList.remove('hidden');
    }
}

// CSRF fetch helper
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]').content; }

async function csrfFetch(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-TOKEN'] = getCsrfToken();
    options.headers['Accept'] = options.headers['Accept'] || 'application/json';
    const response = await fetch(url, options);
    if (response.status === 419) { window.location.reload(); return null; }
    return response;
}

// Device fingerprint
async function generateFingerprint() {
    const data = [
        navigator.userAgent, navigator.language,
        screen.width + 'x' + screen.height, screen.colorDepth,
        new Date().getTimezoneOffset(),
        navigator.hardwareConcurrency || 'unknown', navigator.platform
    ].join('|');
    const hashBuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(data));
    return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}

// Base64url helpers
function base64urlToBuffer(b64) {
    const padding = '='.repeat((4 - b64.length % 4) % 4);
    const base64 = b64.replace(/-/g, '+').replace(/_/g, '/') + padding;
    return Uint8Array.from(atob(base64), c => c.charCodeAt(0)).buffer;
}
function bufferToBase64url(buf) {
    const bytes = new Uint8Array(buf);
    let str = ''; for (const b of bytes) str += String.fromCharCode(b);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Show login error
function showLoginError(msg) {
    const el = document.getElementById('login-error');
    el.textContent = msg;
    el.classList.remove('hidden');
}

// Biometric login (smartphone)
async function startBiometric() {
    if (!window.PublicKeyCredential) { showLoginError(__t.biometricNotSupported); return; }
    try {
        const optRes = await csrfFetch('/auth/passkey/login/options', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
        });
        if (!optRes || !optRes.ok) { showLoginError(__t.noPasskeyFound); return; }
        const options = await optRes.json();
        if (!options.challenge) { showLoginError(__t.noPasskeyFound); return; }

        const publicKeyOptions = {
            challenge: base64urlToBuffer(options.challenge),
            timeout: options.timeout || 60000,
            rpId: options.rpId,
            userVerification: options.userVerification || 'preferred',
        };
        if (options.allowCredentials?.length > 0) {
            publicKeyOptions.allowCredentials = options.allowCredentials.map(c => ({
                id: base64urlToBuffer(c.id), type: c.type, transports: c.transports || ['internal', 'hybrid'],
            }));
        }
        const credential = await navigator.credentials.get({ publicKey: publicKeyOptions });
        const loginRes = await csrfFetch('/auth/passkey/login', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: credential.id, rawId: bufferToBase64url(credential.rawId), type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    signature: bufferToBase64url(credential.response.signature),
                    userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null,
                },
            }),
        });
        if (!loginRes) return;
        const data = await loginRes.json();
        if (data.success && data.device_token) {
            window.location.href = '/auth/token-login/' + data.device_token;
        } else {
            showLoginError(__t.biometricFailed);
        }
    } catch (err) {
        showLoginError(__t.biometricFailed);
    }
}

// QR Code login (desktop)
let qrToken = null, pollInterval = null, timerInterval = null, expiresIn = 300, qrModalOpen = false;

function toggleQrModal() {
    const modal = document.getElementById('qr-modal');
    qrModalOpen = !qrModalOpen;
    if (qrModalOpen) {
        modal.classList.remove('hidden');
        if (!qrToken) generateQr();
    } else {
        modal.classList.add('hidden');
    }
}

async function generateQr() {
    const container = document.getElementById('qr-container');
    container.innerHTML = '<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>';
    document.getElementById('qr-status').textContent = __t.qrLoading;
    document.getElementById('qr-timer').classList.add('hidden');

    try {
        const res = await csrfFetch('/auth/qr/generate', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                browser: navigator.userAgent.match(/(Firefox|Edg|Chrome|Safari)/)?.[0] || 'Unknown',
                os: navigator.platform,
            }),
        });
        if (!res) return;
        const data = await res.json();
        if (data.success) {
            qrToken = data.token;
            expiresIn = 300;
            container.innerHTML = data.qr_svg;
            document.getElementById('qr-status').textContent = __t.scanWithPhone;
            document.getElementById('qr-timer').classList.remove('hidden');
            startPolling();
            startTimer();
        }
    } catch (err) {
        container.innerHTML = '<span class="text-red-500 text-xs">' + __t.qrLoadFailed + '</span>';
    }
}

function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/auth/qr/${qrToken}/status`);
            const data = await res.json();
            if (data.status === 'approved') {
                clearInterval(pollInterval); clearInterval(timerInterval);
                document.getElementById('qr-status').textContent = __t.approved;
                window.location.href = `/auth/qr/complete/${qrToken}`;
            } else if (data.status === 'expired') {
                clearInterval(pollInterval); clearInterval(timerInterval);
                showQrExpired();
            }
        } catch (err) {}
    }, 2000);
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        expiresIn--;
        const mins = Math.floor(expiresIn / 60);
        const secs = expiresIn % 60;
        document.getElementById('timer').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        if (expiresIn <= 0) { clearInterval(timerInterval); clearInterval(pollInterval); showQrExpired(); }
    }, 1000);
}

function showQrExpired() {
    document.getElementById('qr-container').innerHTML = '<button onclick="generateQr()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">' + __t.refreshQr + '</button>';
    document.getElementById('qr-status').textContent = __t.qrExpired;
    document.getElementById('qr-timer').classList.add('hidden');
    qrToken = null;
}

// Register form loading state
document.getElementById('registerForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('register-btn');
    btn.disabled = true; btn.textContent = __t.registering;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
});

// Init: detect platform and show appropriate buttons
(async function() {
    const fingerprint = await generateFingerprint();
    const fpInput = document.getElementById('fingerprint-input');
    if (fpInput) fpInput.value = fingerprint;

    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    const isSmartphone = isTouchDevice && Math.min(screen.width, screen.height) < 768;

    if (isSmartphone) {
        // Smartphone: show biometric button if available
        if (window.PublicKeyCredential) {
            try {
                const canBiometric = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                if (canBiometric) {
                    document.getElementById('biometric-login-btn').classList.remove('hidden');
                }
            } catch(e) { console.error('Biometric check failed:', e); }
        }
    } else {
        // Desktop: show QR button
        document.getElementById('qr-login-btn').classList.remove('hidden');
    }

    // If there are validation errors on register tab, switch to it
    @if($errors->has('organisatie_naam') || $errors->has('naam') || $errors->has('telefoon'))
    switchTab('register');
    @endif
})();
</script>
</body>
</html>

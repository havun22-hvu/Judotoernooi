<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Inloggen') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <style>
        .pin-dot { transition: background-color 0.15s ease; }
        .pin-dot.filled { background-color: #2563eb; }
        .numpad-btn {
            display: flex; align-items: center; justify-content: center;
            width: 100%; aspect-ratio: 1; border-radius: 9999px;
            font-size: 1.5rem; font-weight: 600; color: #1f2937;
            background-color: #f3f4f6; border: 1px solid #e5e7eb;
            cursor: pointer; transition: background-color 0.15s ease;
            user-select: none; -webkit-user-select: none;
        }
        .numpad-btn:hover { background-color: #e5e7eb; }
        .numpad-btn:active { background-color: #d1d5db; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }
        .animate-shake { animation: shake 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        {{-- Taalkiezer --}}
        <div class="flex justify-end mb-2" x-data="{ open: false }">
            <div class="relative">
                <button @click="open = !open" @click.away="open = false" class="flex items-center text-gray-500 hover:text-gray-700 text-sm focus:outline-none">
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
            <p class="text-gray-600">{{ __('Organisator Login') }}</p>
        </div>

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

        <!-- Loading state -->
        <div id="loading-state" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
            <p class="mt-2 text-sm text-gray-400">{{ __('Laden...') }}</p>
        </div>

        <!-- PIN Login Section (known devices) -->
        <div id="pin-login-section" class="hidden">
            <div class="text-center mb-6">
                <p id="welcome-user" class="text-lg font-medium text-gray-800"></p>
                <p class="text-sm text-gray-500">{{ __('Voer je PIN in') }}</p>
            </div>

            <!-- PIN Dots -->
            <div class="flex justify-center gap-3 mb-6">
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            </div>

            <p id="pin-error" class="text-center text-red-500 text-sm mb-4 hidden"></p>

            <!-- Biometric fallback message -->
            <div id="biometric-fallback" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-center">
                <p class="text-sm text-gray-700 mb-2">{{ __('Biometrie niet gelukt? Gebruik een andere methode:') }}</p>
                <div class="flex gap-2 justify-center">
                    <button type="button" onclick="document.getElementById('biometric-fallback').classList.add('hidden')" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                        {{ __('PIN invoeren') }}
                    </button>
                    <button type="button" onclick="showPasswordLogin()" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">
                        {{ __('Wachtwoord') }}
                    </button>
                </div>
            </div>

            <!-- Numpad -->
            <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto">
                <button type="button" onclick="addPin('1')" class="numpad-btn">1</button>
                <button type="button" onclick="addPin('2')" class="numpad-btn">2</button>
                <button type="button" onclick="addPin('3')" class="numpad-btn">3</button>
                <button type="button" onclick="addPin('4')" class="numpad-btn">4</button>
                <button type="button" onclick="addPin('5')" class="numpad-btn">5</button>
                <button type="button" onclick="addPin('6')" class="numpad-btn">6</button>
                <button type="button" onclick="addPin('7')" class="numpad-btn">7</button>
                <button type="button" onclick="addPin('8')" class="numpad-btn">8</button>
                <button type="button" onclick="addPin('9')" class="numpad-btn">9</button>
                <!-- Left: Biometric (mobile) or QR (desktop) -->
                <button type="button" id="biometric-btn" onclick="startBiometric()" class="numpad-btn hidden" style="background-color: #eff6ff;">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                    </svg>
                </button>
                <button type="button" id="qr-btn" onclick="toggleQrModal()" class="numpad-btn hidden" style="background-color: #eff6ff;">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </button>
                <button type="button" onclick="addPin('0')" class="numpad-btn">0</button>
                <button type="button" onclick="removePin()" class="numpad-btn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/>
                    </svg>
                </button>
            </div>

            <!-- Password hint after failed PIN attempts -->
            <div id="password-hint" class="hidden mt-4">
                <button type="button" onclick="showPasswordLogin()" class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    {{ __('Inloggen met wachtwoord') }}
                </button>
            </div>

            <div class="text-center mt-4">
                <button type="button" onclick="showPasswordLogin()" class="text-sm text-blue-600 hover:underline">
                    {{ __('Ander account? Login met wachtwoord') }}
                </button>
            </div>
        </div>

        <!-- QR Login Modal -->
        <div id="qr-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50" onclick="if(event.target === this) toggleQrModal()">
            <div class="bg-white rounded-2xl p-6 m-4 max-w-sm w-full shadow-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Inloggen met telefoon') }}</h3>
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

        <!-- Password Login Form -->
        <div id="password-login-section" class="hidden">
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
                           class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
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
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 pr-10 focus:border-blue-500 focus:ring-blue-500">
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
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    {{ __('Inloggen') }}
                </button>
            </form>

            {{-- DO NOT REMOVE: Registration link for new users --}}
            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    {{ __('Nog geen account?') }}
                    <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ __('Registreer hier') }}
                    </a>
                </p>
            </div>
        </div>

    </div>

<script>
let deviceFingerprint = null;
let currentPin = '';
let pinAttempts = 0;

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function csrfFetch(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-TOKEN'] = getCsrfToken();
    options.headers['Accept'] = options.headers['Accept'] || 'application/json';
    const response = await fetch(url, options);
    if (response.status === 419) {
        window.location.reload();
        return null;
    }
    return response;
}

async function generateFingerprint() {
    const data = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        screen.colorDepth,
        new Date().getTimezoneOffset(),
        navigator.hardwareConcurrency || 'unknown',
        navigator.platform
    ].join('|');
    const encoder = new TextEncoder();
    const hashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(data));
    return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}

function togglePassword() {
    const input = document.getElementById('password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
    } else {
        input.type = 'password';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
    }
}

function showPasswordLogin() {
    document.getElementById('pin-login-section').classList.add('hidden');
    document.getElementById('password-login-section').classList.remove('hidden');
    document.getElementById('email')?.focus();
}

function addPin(digit) {
    if (currentPin.length >= 5) return;
    currentPin += digit;
    updatePinDots();
    if (currentPin.length === 5) submitPin();
}

function removePin() {
    currentPin = currentPin.slice(0, -1);
    updatePinDots();
    hidePinError();
}

function updatePinDots() {
    document.querySelectorAll('.pin-dot').forEach((dot, i) => {
        dot.classList.toggle('filled', i < currentPin.length);
    });
}

function showPinError(msg) {
    const el = document.getElementById('pin-error');
    el.textContent = msg;
    el.classList.remove('hidden');
    document.querySelectorAll('.pin-dot').forEach(dot => {
        dot.classList.add('animate-shake');
        setTimeout(() => dot.classList.remove('animate-shake'), 300);
    });
    currentPin = '';
    updatePinDots();
}

function hidePinError() {
    document.getElementById('pin-error').classList.add('hidden');
}

async function submitPin() {
    try {
        const res = await csrfFetch('/auth/pin/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint: deviceFingerprint, pin: currentPin }),
        });
        if (!res) return;
        const data = await res.json();
        if (data.success) {
            window.location.href = data.redirect || '/';
        } else {
            pinAttempts++;
            if (pinAttempts >= 3) {
                showPinError('PIN 3x fout. Probeer wachtwoord.');
                showPasswordHint();
            } else {
                showPinError(data.message || 'Onjuiste PIN');
            }
        }
    } catch (err) {
        showPinError('Er ging iets mis');
    }
}

async function startBiometric() {
    if (!window.PublicKeyCredential) {
        showPinError('Biometrie niet ondersteund');
        return;
    }
    try {
        const optRes = await csrfFetch('/auth/passkey/login/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        });
        if (!optRes || !optRes.ok) { showPinError('Geen passkey gevonden'); return; }
        const options = await optRes.json();
        if (!options.challenge) { showPinError('Geen passkey gevonden'); return; }

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
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    signature: bufferToBase64url(credential.response.signature),
                    userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null,
                },
            }),
        });
        if (!loginRes) return;
        const loginData = await loginRes.json();
        if (loginData.success && loginData.device_token) {
            window.location.href = '/auth/token-login/' + loginData.device_token;
        } else {
            showPinError('Biometrie mislukt');
        }
    } catch (err) {
        // Any biometric failure → show fallback options
        showBiometricFallback();
    }
}

function showBiometricFallback() {
    document.getElementById('pin-error').classList.add('hidden');
    document.getElementById('biometric-fallback').classList.remove('hidden');
}

function showPasswordHint() {
    document.getElementById('password-hint').classList.remove('hidden');
}

// QR Code functions
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
    document.getElementById('qr-status').textContent = 'QR code laden...';
    document.getElementById('qr-timer').classList.add('hidden');

    try {
        const res = await csrfFetch('/auth/qr/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
            const approveUrl = data.approve_url;
            container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(approveUrl)}" alt="QR" class="rounded">`;
            document.getElementById('qr-status').textContent = 'Scan met je telefoon';
            document.getElementById('qr-timer').classList.remove('hidden');
            startPolling();
            startTimer();
        }
    } catch (err) {
        container.innerHTML = '<span class="text-red-500 text-xs">QR laden mislukt</span>';
    }
}

function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/auth/qr/${qrToken}/status`);
            const data = await res.json();
            if (data.status === 'approved') {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                document.getElementById('qr-status').textContent = 'Goedgekeurd! Doorsturen...';
                window.location.href = `/auth/qr/complete/${qrToken}`;
            } else if (data.status === 'expired') {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
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
        if (expiresIn <= 0) {
            clearInterval(timerInterval);
            clearInterval(pollInterval);
            showQrExpired();
        }
    }, 1000);
}

function showQrExpired() {
    document.getElementById('qr-container').innerHTML = '<button onclick="generateQr()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Vernieuw QR</button>';
    document.getElementById('qr-status').textContent = 'QR code verlopen';
    document.getElementById('qr-timer').classList.add('hidden');
    qrToken = null;
}

// Base64url helpers
function base64urlToBuffer(b64) {
    const padding = '='.repeat((4 - b64.length % 4) % 4);
    const base64 = b64.replace(/-/g, '+').replace(/_/g, '/') + padding;
    return Uint8Array.from(atob(base64), c => c.charCodeAt(0)).buffer;
}
function bufferToBase64url(buf) {
    const bytes = new Uint8Array(buf);
    let str = '';
    for (const b of bytes) str += String.fromCharCode(b);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Keyboard support for PIN numpad
document.addEventListener('keydown', e => {
    if (document.getElementById('pin-login-section').classList.contains('hidden')) return;
    if (e.key >= '0' && e.key <= '9') { e.preventDefault(); addPin(e.key); }
    else if (e.key === 'Backspace') { e.preventDefault(); removePin(); }
    else if (e.key === 'Enter' && currentPin.length === 5) { e.preventDefault(); submitPin(); }
});

// Init: check device fingerprint
(async function() {
    deviceFingerprint = await generateFingerprint();

    // Set fingerprint in hidden field for password login form
    const fpInput = document.getElementById('fingerprint-input');
    if (fpInput) fpInput.value = deviceFingerprint;

    try {
        const res = await csrfFetch('/auth/pin/check-device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint: deviceFingerprint }),
        });
        if (!res) return;
        const data = await res.json();
        document.getElementById('loading-state').classList.add('hidden');

        // Biometric only on smartphones (fingerprint), QR only on desktop
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        const isSmartphone = isTouchDevice && Math.min(screen.width, screen.height) < 550;
        let canBiometric = false;
        if (isSmartphone && window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            try { canBiometric = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(); } catch(e) {}
        }

        if (data.has_device && data.has_pin) {
            document.getElementById('pin-login-section').classList.remove('hidden');
            document.getElementById('welcome-user').textContent = `Welkom terug${data.user_name ? ', ' + data.user_name : ''}!`;
            if (canBiometric && data.has_biometric && window.PublicKeyCredential) {
                // Smartphone with fingerprint + passkey registered → biometric button + auto-start
                document.getElementById('biometric-btn').classList.remove('hidden');
                setTimeout(() => startBiometric(), 500);
            } else if (!isTouchDevice) {
                // Desktop → QR button
                document.getElementById('qr-btn').classList.remove('hidden');
            }
        } else {
            document.getElementById('password-login-section').classList.remove('hidden');
        }
    } catch (err) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('password-login-section').classList.remove('hidden');
    }
})();
</script>
</body>
</html>

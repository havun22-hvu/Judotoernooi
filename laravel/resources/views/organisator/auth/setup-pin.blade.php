<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('PIN instellen') }} - {{ __('JudoToernooi') }}</title>
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
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
            <p class="text-gray-600" id="step-title">{{ __('Kies een 5-cijferige PIN') }}</p>
            <p class="text-sm text-gray-400 mt-1" id="step-subtitle">{{ __('Hiermee kun je sneller inloggen op dit apparaat') }}</p>
        </div>

        <!-- PIN Section -->
        <div id="pin-section">
            <!-- PIN Dots -->
            <div class="flex justify-center gap-3 mb-6">
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
                <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            </div>

            <p id="pin-error" class="text-center text-red-500 text-sm mb-4 hidden"></p>

            <!-- Numpad -->
            <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto" id="numpad">
                <button type="button" onclick="addPin('1')" class="numpad-btn">1</button>
                <button type="button" onclick="addPin('2')" class="numpad-btn">2</button>
                <button type="button" onclick="addPin('3')" class="numpad-btn">3</button>
                <button type="button" onclick="addPin('4')" class="numpad-btn">4</button>
                <button type="button" onclick="addPin('5')" class="numpad-btn">5</button>
                <button type="button" onclick="addPin('6')" class="numpad-btn">6</button>
                <button type="button" onclick="addPin('7')" class="numpad-btn">7</button>
                <button type="button" onclick="addPin('8')" class="numpad-btn">8</button>
                <button type="button" onclick="addPin('9')" class="numpad-btn">9</button>
                <div></div>
                <button type="button" onclick="addPin('0')" class="numpad-btn">0</button>
                <button type="button" onclick="removePin()" class="numpad-btn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Biometric Setup Section (shown after PIN is set, on touch devices) -->
        <div id="biometric-section" class="hidden">
            <div class="text-center py-4">
                <div class="w-20 h-20 mx-auto mb-4 bg-blue-50 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                    </svg>
                </div>
                <p class="text-gray-700 mb-2">{{ __('PIN is ingesteld!') }}</p>
                <p class="text-sm text-gray-500 mb-6">{{ __('Wil je ook inloggen met je vingerafdruk of gezichtsherkenning?') }}</p>

                <button onclick="registerPasskey()" id="biometric-enable-btn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors mb-3">
                    {{ __('Ja, biometrie inschakelen') }}
                </button>

                <p id="biometric-error" class="text-red-500 text-sm mt-2 hidden"></p>
                <p id="biometric-success" class="text-green-500 text-sm mt-2 hidden"></p>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="{{ auth('organisator')->user()?->isSitebeheerder() ? route('admin.index') : route('organisator.dashboard', ['organisator' => auth('organisator')->user()?->slug ?? '']) }}"
               id="skip-link" class="text-sm text-gray-500 hover:underline">
                {{ __('Overslaan') }}
            </a>
        </div>
    </div>

<script>
let deviceFingerprint = null;
let currentPin = '';
let firstPin = null;
let step = 1; // 1 = choose, 2 = confirm

const dashboardUrl = @json(auth('organisator')->user()?->isSitebeheerder() ? route('admin.index') : route('organisator.dashboard', ['organisator' => auth('organisator')->user()?->slug ?? '']));

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function generateFingerprint() {
    const data = [
        navigator.userAgent, navigator.language,
        screen.width + 'x' + screen.height, screen.colorDepth,
        new Date().getTimezoneOffset(),
        navigator.hardwareConcurrency || 'unknown', navigator.platform
    ].join('|');
    const encoder = new TextEncoder();
    const hashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(data));
    return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
}

function addPin(digit) {
    if (currentPin.length >= 5) return;
    currentPin += digit;
    updatePinDots();
    if (currentPin.length === 5) {
        if (step === 1) {
            firstPin = currentPin;
            currentPin = '';
            step = 2;
            updatePinDots();
            document.getElementById('step-title').textContent = 'Bevestig je PIN';
            document.getElementById('step-subtitle').textContent = 'Voer dezelfde PIN nogmaals in';
        } else {
            confirmPin();
        }
    }
}

function removePin() {
    currentPin = currentPin.slice(0, -1);
    updatePinDots();
    document.getElementById('pin-error').classList.add('hidden');
}

function updatePinDots() {
    document.querySelectorAll('.pin-dot').forEach((dot, i) => {
        dot.classList.toggle('filled', i < currentPin.length);
    });
}

async function confirmPin() {
    if (currentPin !== firstPin) {
        document.getElementById('pin-error').textContent = 'PINs komen niet overeen. Probeer opnieuw.';
        document.getElementById('pin-error').classList.remove('hidden');
        document.querySelectorAll('.pin-dot').forEach(dot => {
            dot.classList.add('animate-shake');
            setTimeout(() => dot.classList.remove('animate-shake'), 300);
        });
        currentPin = '';
        firstPin = null;
        step = 1;
        updatePinDots();
        document.getElementById('step-title').textContent = 'Kies een 5-cijferige PIN';
        document.getElementById('step-subtitle').textContent = 'Hiermee kun je sneller inloggen op dit apparaat';
        return;
    }

    try {
        const res = await fetch('/auth/pin/setup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ fingerprint: deviceFingerprint, pin: currentPin }),
        });
        const data = await res.json();
        if (data.success) {
            // Biometric only on smartphones (small screen = likely has fingerprint sensor)
            // Tablets (Samsung Tab A etc) get PIN/pattern prompt instead of fingerprint → skip
            const isSmartphone = Math.min(screen.width, screen.height) < 550;
            let canBiometric = false;
            if (isSmartphone && window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                try {
                    canBiometric = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                } catch (e) { canBiometric = false; }
            }
            if (canBiometric) {
                showBiometricSetup();
            } else {
                // No platform authenticator: skip biometric, go to dashboard
                document.getElementById('step-title').textContent = 'PIN ingesteld!';
                document.getElementById('step-subtitle').textContent = 'Doorsturen naar dashboard...';
                document.getElementById('pin-section').classList.add('hidden');
                setTimeout(() => { window.location.href = dashboardUrl; }, 1000);
            }
        } else {
            document.getElementById('pin-error').textContent = data.message || 'Fout bij instellen PIN';
            document.getElementById('pin-error').classList.remove('hidden');
        }
    } catch (err) {
        document.getElementById('pin-error').textContent = 'Er ging iets mis';
        document.getElementById('pin-error').classList.remove('hidden');
    }
}

function showBiometricSetup() {
    document.getElementById('pin-section').classList.add('hidden');
    document.getElementById('biometric-section').classList.remove('hidden');
    document.getElementById('step-title').textContent = 'Biometrie instellen';
    document.getElementById('step-subtitle').textContent = '';
    document.getElementById('skip-link').textContent = 'Nee, alleen PIN gebruiken';
    document.getElementById('skip-link').href = dashboardUrl;
}

async function registerPasskey() {
    const btn = document.getElementById('biometric-enable-btn');
    btn.disabled = true;
    btn.textContent = 'Bezig met registreren...';

    try {
        // Step 1: Get registration options from server
        const optRes = await fetch('/auth/passkey/register/options', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (!optRes.ok) {
            throw new Error('Kon passkey opties niet ophalen');
        }

        const options = await optRes.json();

        // Step 2: Create credential via browser WebAuthn API (triggers biometric prompt)
        const publicKeyOptions = {
            challenge: base64urlToBuffer(options.challenge),
            rp: options.rp,
            user: {
                id: base64urlToBuffer(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName,
            },
            pubKeyCredParams: options.pubKeyCredParams,
            timeout: options.timeout || 60000,
            authenticatorSelection: options.authenticatorSelection || {
                authenticatorAttachment: 'platform',
                userVerification: 'required',
            },
            attestation: options.attestation || 'none',
        };

        if (options.excludeCredentials?.length > 0) {
            publicKeyOptions.excludeCredentials = options.excludeCredentials.map(c => ({
                id: base64urlToBuffer(c.id),
                type: c.type,
                transports: c.transports || ['internal'],
            }));
        }

        const credential = await navigator.credentials.create({ publicKey: publicKeyOptions });

        // Step 3: Send credential to server for storage
        const regRes = await fetch('/auth/passkey/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    attestationObject: bufferToBase64url(credential.response.attestationObject),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                },
            }),
        });

        const regData = await regRes.json();

        if (regData.success) {
            // Also mark device as biometric enabled
            await fetch('/auth/pin/biometric', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ fingerprint: deviceFingerprint }),
            });

            document.getElementById('biometric-success').textContent = 'Biometrie ingeschakeld! Doorsturen...';
            document.getElementById('biometric-success').classList.remove('hidden');
            btn.classList.add('hidden');
            setTimeout(() => { window.location.href = dashboardUrl; }, 1000);
        } else {
            throw new Error(regData.message || 'Registratie mislukt');
        }
    } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Ja, biometrie inschakelen';
        if (err.name === 'NotAllowedError') {
            document.getElementById('biometric-error').textContent = 'Biometrie geannuleerd. Je kunt het later nog inschakelen.';
        } else {
            document.getElementById('biometric-error').textContent = err.message || 'Biometrie niet beschikbaar op dit apparaat.';
        }
        document.getElementById('biometric-error').classList.remove('hidden');
    }
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

// Keyboard support
document.addEventListener('keydown', e => {
    if (document.getElementById('pin-section').classList.contains('hidden')) return;
    if (e.key >= '0' && e.key <= '9') { e.preventDefault(); addPin(e.key); }
    else if (e.key === 'Backspace') { e.preventDefault(); removePin(); }
});

// Init
(async function() {
    deviceFingerprint = await generateFingerprint();
})();
</script>
</body>
</html>

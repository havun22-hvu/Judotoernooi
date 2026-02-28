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

        <!-- PIN Dots -->
        <div class="flex justify-center gap-3 mb-6">
            <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
            <div class="pin-dot w-4 h-4 rounded-full border-2 border-blue-500 bg-transparent"></div>
        </div>

        <p id="pin-error" class="text-center text-red-500 text-sm mb-4 hidden"></p>
        <p id="pin-success" class="text-center text-green-500 text-sm mb-4 hidden"></p>

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

        <div class="text-center mt-6">
            <a href="{{ auth('organisator')->user()?->isSitebeheerder() ? route('admin.index') : route('organisator.dashboard', ['organisator' => auth('organisator')->user()?->slug ?? '']) }}"
               class="text-sm text-gray-500 hover:underline">
                {{ __('Overslaan') }}
            </a>
        </div>
    </div>

<script>
let deviceFingerprint = null;
let currentPin = '';
let firstPin = null;
let step = 1; // 1 = choose, 2 = confirm

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
            document.getElementById('pin-success').textContent = 'PIN ingesteld! Doorsturen...';
            document.getElementById('pin-success').classList.remove('hidden');
            document.getElementById('numpad').style.opacity = '0.5';
            document.getElementById('numpad').style.pointerEvents = 'none';

            // Check biometric support on mobile
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isMobile && window.PublicKeyCredential) {
                await enableBiometric();
            }

            setTimeout(() => {
                @if(auth('organisator')->user()?->isSitebeheerder())
                    window.location.href = '{{ route('admin.index') }}';
                @else
                    window.location.href = '{{ route('organisator.dashboard', ['organisator' => auth('organisator')->user()?->slug ?? '']) }}';
                @endif
            }, 1000);
        } else {
            document.getElementById('pin-error').textContent = data.message || 'Fout bij instellen PIN';
            document.getElementById('pin-error').classList.remove('hidden');
        }
    } catch (err) {
        document.getElementById('pin-error').textContent = 'Er ging iets mis';
        document.getElementById('pin-error').classList.remove('hidden');
    }
}

async function enableBiometric() {
    try {
        await fetch('/auth/pin/biometric', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ fingerprint: deviceFingerprint }),
        });
    } catch (err) {
        // Biometric is optional, don't block flow
    }
}

// Keyboard support
document.addEventListener('keydown', e => {
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

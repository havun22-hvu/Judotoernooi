<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Kaart Scan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        @if(!$isGeldig)
        {{-- INVALID - Not activated or no photo --}}
        <div class="bg-red-500 text-white px-4 py-6 text-center">
            <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <h1 class="text-2xl font-bold">ONGELDIG</h1>
            <p class="text-red-100 mt-1">Kaart niet geactiveerd of geen foto</p>
        </div>
        <div class="p-6 text-center">
            <p class="text-gray-600 mb-4">Deze coach kaart is nog niet correct geactiveerd.</p>
            <p class="text-red-600 font-bold text-lg">GEEN TOEGANG</p>
        </div>
        @else
        {{-- Status header --}}
        <div class="{{ $wasAlreadyScanned ? 'bg-yellow-500' : 'bg-green-500' }} text-white px-4 py-4 text-center">
            @if($wasAlreadyScanned)
            <svg class="w-12 h-12 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h1 class="text-xl font-bold">Al Eerder Gescand</h1>
            <p class="text-yellow-100 text-sm">{{ $coachKaart->gescand_op?->format('d-m-Y H:i') }}</p>
            @else
            <svg class="w-12 h-12 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h1 class="text-xl font-bold">TOEGANG GELDIG</h1>
            @endif
        </div>

        {{-- GROTE FOTO VOOR VERIFICATIE --}}
        <div class="p-4 bg-gray-50">
            <p class="text-center text-gray-600 text-sm mb-2 font-medium">⚠️ CONTROLEER: komt deze persoon overeen met de foto?</p>
            @if($coachKaart->foto)
            <div class="flex justify-center">
                <img src="{{ $coachKaart->getFotoUrl() }}" alt="Foto {{ $coachKaart->naam }}"
                     class="w-48 h-48 object-cover rounded-xl border-4 {{ $wasAlreadyScanned ? 'border-yellow-400' : 'border-green-400' }} shadow-lg">
            </div>
            @endif
        </div>

        {{-- Coach info --}}
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between bg-purple-100 rounded-lg px-4 py-3">
                <div>
                    <p class="text-2xl font-black text-purple-900">{{ $coachKaart->naam }}</p>
                    <p class="text-purple-700 font-medium">{{ $coachKaart->club->naam }}</p>
                </div>
                <span class="bg-purple-600 text-white font-black px-3 py-1 rounded">COACH</span>
            </div>

            {{-- Transfer history --}}
            @if($wisselingen->count() > 1)
            <div class="bg-gray-50 rounded-lg px-4 py-3 mt-2">
                <p class="text-gray-500 text-xs uppercase font-medium mb-2">Wisselgeschiedenis</p>
                <div class="space-y-1">
                    @foreach($wisselingen as $wisseling)
                    <div class="flex items-center gap-2 text-sm {{ $wisseling->isHuidigeCoach() ? 'text-purple-700 font-medium' : 'text-gray-500' }}">
                        <span class="w-12 text-xs">{{ $wisseling->geactiveerd_op->format('H:i') }}</span>
                        @if($wisseling->foto)
                        <img src="{{ $wisseling->getFotoUrl() }}" alt="{{ $wisseling->naam }}"
                             class="w-6 h-6 rounded-full object-cover border {{ $wisseling->isHuidigeCoach() ? 'border-purple-400' : 'border-gray-300' }}">
                        @else
                        <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-400">?</div>
                        @endif
                        <span>{{ $wisseling->naam }}</span>
                        @if($wisseling->isHuidigeCoach())
                        <span class="text-xs bg-purple-200 text-purple-700 px-1.5 py-0.5 rounded">huidig</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="text-center text-xs text-gray-400 pt-1">
                {{ $coachKaart->toernooi->naam }} • {{ $coachKaart->qr_code }}
            </div>
        </div>

        {{-- Check-in/Check-out (alleen als actief) --}}
        @if($coachKaart->toernooi->coach_incheck_actief)
        <div class="px-4 py-3 border-t">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-gray-600">Status:</span>
                @if($coachKaart->isIngecheckt())
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                        ✅ Ingecheckt sinds {{ $coachKaart->ingecheckt_op->format('H:i') }}
                    </span>
                @else
                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-medium">
                        ⬚ Niet ingecheckt
                    </span>
                @endif
            </div>

            @if($coachKaart->isIngecheckt())
                <form action="{{ route('coach-kaart.checkout', $coachKaart->qr_code) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium">
                        🚪 Check uit
                    </button>
                </form>
            @else
                <form action="{{ route('coach-kaart.checkin', $coachKaart->qr_code) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium">
                        ✓ Check in
                    </button>
                </form>
            @endif
        </div>
        @endif

        {{-- Footer --}}
        <div class="px-4 py-3 {{ $wasAlreadyScanned ? 'bg-yellow-50' : 'bg-green-50' }} border-t text-center">
            <p class="text-sm {{ $wasAlreadyScanned ? 'text-yellow-700' : 'text-green-700' }} font-medium">
                🥋 {{ $wasAlreadyScanned ? 'Let op: kaart al eerder gebruikt' : 'Toegang tot de Dojo' }}
            </p>
        </div>
        @endif

        {{-- Back to scanner button --}}
        <div class="p-4 border-t">
            <button onclick="history.back()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium">
                ← Volgende scan
            </button>
        </div>
    </div>
</body>
</html>

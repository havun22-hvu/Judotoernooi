@extends('layouts.app')

@section('title', __('Account Instellingen'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">{{ __('Account Instellingen') }}</h1>

    {{-- Profielgegevens --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Profielgegevens') }}</h2>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('auth.account.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="naam" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Naam') }} *</label>
                    <input type="text" name="naam" id="naam" value="{{ old('naam', $organisator->naam) }}"
                           class="w-full border rounded-lg px-3 py-2 @error('naam') border-red-500 @enderror" required>
                    @error('naam')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('E-mail') }} *</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $organisator->email) }}"
                           class="w-full border rounded-lg px-3 py-2 @error('email') border-red-500 @enderror" required>
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="telefoon" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Telefoon') }}</label>
                    <input type="text" name="telefoon" id="telefoon" value="{{ old('telefoon', $organisator->telefoon) }}"
                           class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                    <label for="locale" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Taal') }}</label>
                    <select name="locale" id="locale" class="w-full border rounded-lg px-3 py-2">
                        <option value="nl" {{ $organisator->locale === 'nl' ? 'selected' : '' }}>Nederlands</option>
                        <option value="en" {{ $organisator->locale === 'en' ? 'selected' : '' }}>English</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    {{ __('Opslaan') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Wachtwoord wijzigen --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Wachtwoord wijzigen') }}</h2>

        @if(session('password_success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">
                {{ session('password_success') }}
            </div>
        @endif

        <form action="{{ route('auth.account.password') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Huidig wachtwoord') }}</label>
                    <input type="password" name="current_password" id="current_password"
                           class="w-full border rounded-lg px-3 py-2 @error('current_password') border-red-500 @enderror" required>
                    @error('current_password')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nieuw wachtwoord') }}</label>
                        <input type="password" name="password" id="password"
                               class="w-full border rounded-lg px-3 py-2 @error('password') border-red-500 @enderror" required>
                        @error('password')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Bevestig wachtwoord') }}</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="w-full border rounded-lg px-3 py-2" required>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    {{ __('Wachtwoord wijzigen') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Gekoppelde apparaten --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('Gekoppelde apparaten') }}</h2>

        @if(session('device_success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4">
                {{ session('device_success') }}
            </div>
        @endif

        @if($devices->isEmpty())
            <p class="text-gray-500">{{ __('Geen apparaten gekoppeld.') }}</p>
        @else
            <div class="space-y-3">
                @foreach($devices as $device)
                    <div class="flex items-center justify-between border rounded-lg p-4 {{ $device->device_fingerprint === request()->cookie('device_fingerprint') ? 'border-blue-400 bg-blue-50' : '' }}">
                        <div>
                            <div class="font-medium">
                                {{ $device->device_name }}
                                @if($device->device_fingerprint === request()->cookie('device_fingerprint'))
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full ml-1">{{ __('Dit apparaat') }}</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $device->browser }} · {{ $device->os }}
                                @if($device->last_used_at)
                                    · {{ __('Laatst gebruikt') }}: {{ $device->last_used_at->diffForHumans() }}
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                @if($device->hasPin())
                                    <span class="text-green-600">PIN</span>
                                @endif
                                @if($device->has_biometric)
                                    <span class="text-green-600 ml-2">{{ __('Biometrie') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($device->device_fingerprint !== request()->cookie('device_fingerprint'))
                                <form action="{{ route('auth.account.device.remove', $device->id) }}" method="POST"
                                      onsubmit="return confirm('{{ __('Weet je zeker dat je dit apparaat wilt verwijderen?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                        {{ __('Verwijderen') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex gap-3">
            <a href="{{ route('auth.setup-pin') }}" class="text-sm text-blue-600 hover:text-blue-800">
                {{ __('PIN instellen voor dit apparaat') }}
            </a>
        </div>
    </div>

    <div class="text-center">
        <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700">← {{ __('Terug') }}</a>
    </div>
</div>
@endsection

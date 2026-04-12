@extends('layouts.app')

@section('title', 'System Alerts')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">System Alerts</h1>
        <div class="flex gap-3">
            {{-- Filter buttons --}}
            <a href="{{ route('admin.alerts') }}" class="px-3 py-1 rounded text-sm {{ !request('type') && !request('unread') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">Alle</a>
            <a href="{{ route('admin.alerts', ['unread' => 1]) }}" class="px-3 py-1 rounded text-sm {{ request('unread') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">Ongelezen</a>
            <a href="{{ route('admin.alerts', ['type' => 'autofix']) }}" class="px-3 py-1 rounded text-sm {{ request('type') === 'autofix' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">AutoFix</a>
            <a href="{{ route('admin.alerts', ['type' => 'queue_failure']) }}" class="px-3 py-1 rounded text-sm {{ request('type') === 'queue_failure' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">Queue</a>

            {{-- Mark all read --}}
            <form action="{{ route('admin.alerts.markAllRead') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1 rounded text-sm bg-green-600 text-white hover:bg-green-700">Alles gelezen</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
    @endif

    @if($alerts->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            Geen alerts gevonden.
        </div>
    @else
        <div class="space-y-3">
            @foreach($alerts as $alert)
                <div class="bg-white rounded-lg shadow p-4 flex items-start gap-4 {{ !$alert->is_read ? 'border-l-4 border-' . $alert->severity_color . '-500' : 'opacity-75' }}">
                    {{-- Severity indicator --}}
                    <div class="flex-shrink-0 mt-1">
                        @switch($alert->severity)
                            @case('critical')
                                <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                                @break
                            @case('high')
                                <span class="inline-block w-3 h-3 rounded-full bg-orange-500"></span>
                                @break
                            @case('medium')
                                <span class="inline-block w-3 h-3 rounded-full bg-yellow-500"></span>
                                @break
                            @default
                                <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                        @endswitch
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block px-2 py-0.5 text-xs rounded font-medium
                                {{ $alert->type === 'autofix' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $alert->type === 'queue_failure' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $alert->type === 'security' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $alert->type === 'slow_query' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $alert->type === 'health_degraded' ? 'bg-orange-100 text-orange-800' : '' }}
                            ">{{ $alert->type }}</span>
                            <span class="text-xs text-gray-500">{{ $alert->created_at->diffForHumans() }}</span>
                            @if($alert->source)
                                <span class="text-xs text-gray-400">{{ $alert->source }}</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-gray-800 {{ !$alert->is_read ? '' : 'font-normal' }}">{{ $alert->title }}</h3>
                        @if($alert->message)
                            <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ Str::limit($alert->message, 300) }}</p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex-shrink-0">
                        @if(!$alert->is_read)
                            <form action="{{ route('admin.alerts.markRead', $alert) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-sm text-blue-600 hover:text-blue-800" title="Markeer als gelezen">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $alerts->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection

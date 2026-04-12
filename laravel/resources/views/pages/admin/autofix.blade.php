@extends('layouts.app')

@section('title', 'AutoFix Overzicht')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">AutoFix Overzicht</h1>
        <p class="text-gray-500 mt-1">AI-gestuurde error analyse en automatische fixes</p>
    </div>
    <a href="{{ route('admin.index') }}" class="text-blue-600 hover:text-blue-800">
        &larr; Terug naar Dashboard
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
        <div class="text-sm text-gray-500">Totaal</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-green-600">{{ $stats['applied'] }}</div>
        <div class="text-sm text-gray-500">Toegepast</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</div>
        <div class="text-sm text-gray-500">Mislukt</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
        <div class="text-sm text-gray-500">In behandeling</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-orange-600">{{ $stats['errors'] }}</div>
        <div class="text-sm text-gray-500">Errors</div>
    </div>
</div>

{{-- Aggregate Metrics --}}
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Metrics</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        {{-- Success rate 7 days --}}
        <div>
            <div class="text-sm text-gray-500 mb-1">Succesrate (7 dagen)</div>
            <div class="text-2xl font-bold {{ ($metrics['success_rate_7d'] ?? 0) >= 50 ? 'text-green-600' : 'text-red-600' }}">
                {{ $metrics['success_rate_7d'] !== null ? $metrics['success_rate_7d'] . '%' : '-' }}
            </div>
        </div>

        {{-- Success rate 30 days --}}
        <div>
            <div class="text-sm text-gray-500 mb-1">Succesrate (30 dagen)</div>
            <div class="text-2xl font-bold {{ ($metrics['success_rate_30d'] ?? 0) >= 50 ? 'text-green-600' : 'text-red-600' }}">
                {{ $metrics['success_rate_30d'] !== null ? $metrics['success_rate_30d'] . '%' : '-' }}
            </div>
        </div>

        {{-- Avg time to fix --}}
        <div>
            <div class="text-sm text-gray-500 mb-1">Gem. fix-tijd</div>
            <div class="text-2xl font-bold text-blue-600">
                @if($metrics['avg_time_to_fix_minutes'] !== null)
                    @if($metrics['avg_time_to_fix_minutes'] < 60)
                        {{ $metrics['avg_time_to_fix_minutes'] }} min
                    @else
                        {{ round($metrics['avg_time_to_fix_minutes'] / 60, 1) }} uur
                    @endif
                @else
                    -
                @endif
            </div>
        </div>

        {{-- Trend --}}
        <div>
            <div class="text-sm text-gray-500 mb-1">Trend (7d vs vorige 7d)</div>
            <div class="text-2xl font-bold {{ $metrics['trend_direction'] === 'down' ? 'text-green-600' : ($metrics['trend_direction'] === 'up' ? 'text-red-600' : 'text-gray-600') }}">
                @if($metrics['trend_direction'] === 'down')
                    &#9660; {{ abs($metrics['trend_percentage']) }}% minder
                @elseif($metrics['trend_direction'] === 'up')
                    &#9650; {{ $metrics['trend_percentage'] }}% meer
                @else
                    &#8212; Stabiel
                @endif
            </div>
            <div class="text-xs text-gray-400">{{ $metrics['current_week_count'] }} vs {{ $metrics['previous_week_count'] }}</div>
        </div>
    </div>

    {{-- Common error types --}}
    @if($metrics['common_errors']->isNotEmpty())
        <div class="mt-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Meest voorkomende errors</h3>
            <div class="space-y-1">
                @foreach($metrics['common_errors'] as $error)
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-mono text-gray-700" title="{{ $error['full_class'] }}">{{ $error['class'] }}</span>
                        <span class="text-gray-500 font-medium">{{ $error['count'] }}x</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Proposals --}}
<div class="space-y-3">
    @forelse($proposals as $proposal)
        @php
            $statusColor = match($proposal->status) {
                'applied' => 'green',
                'failed' => 'red',
                'pending' => 'yellow',
                'error' => 'orange',
                'notify_only' => 'purple',
                'dry_run' => 'blue',
                default => 'gray',
            };
            $statusLabel = match($proposal->status) {
                'applied' => 'Toegepast',
                'failed' => 'Mislukt',
                'pending' => 'In behandeling',
                'error' => 'Error',
                'notify_only' => 'Alleen melding',
                'dry_run' => 'Dry run',
                default => $proposal->status,
            };
            // Extract just the filename from the path
            $shortFile = basename($proposal->file);
            // Parse ANALYSIS line from claude_analysis
            $analysisLine = '';
            if ($proposal->claude_analysis && preg_match('/ANALYSIS:\s*(.+?)(?:\n|$)/i', $proposal->claude_analysis, $m)) {
                $analysisLine = trim($m[1]);
            }
        @endphp

        <div class="bg-white rounded-lg shadow">
            {{-- Header row - clickable --}}
            <button onclick="toggleDetail({{ $proposal->id }})" class="w-full text-left px-4 py-3 hover:bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800 whitespace-nowrap">
                            {{ $statusLabel }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900">
                                {{ class_basename($proposal->exception_class) }}
                                <span class="text-gray-400 font-normal">in {{ $shortFile }}:{{ $proposal->line }}</span>
                            </div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ Str::limit($proposal->exception_message, 100) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 ml-4 shrink-0">
                        @if($proposal->organisator_naam)
                            <span class="text-xs text-gray-500">{{ $proposal->organisator_naam }}</span>
                        @endif
                        <span class="text-xs text-gray-400">{{ $proposal->created_at->format('d-m H:i') }}</span>
                        <svg id="arrow-{{ $proposal->id }}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                {{-- Claude's samenvatting --}}
                @if($analysisLine)
                    <div class="mt-1 text-xs text-blue-600 italic">
                        Claude: {{ Str::limit($analysisLine, 120) }}
                    </div>
                @endif
            </button>

            {{-- Detail panel --}}
            <div id="detail-{{ $proposal->id }}" class="hidden border-t border-gray-100 px-4 py-4 bg-gray-50 rounded-b-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="space-y-2">
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Bestand</span>
                            <div class="text-sm font-mono text-gray-700">{{ $proposal->file }}:{{ $proposal->line }}</div>
                        </div>
                        @if($proposal->url)
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">URL</span>
                            <div class="text-sm text-gray-700 break-all">{{ $proposal->http_method }} {{ $proposal->url }}</div>
                        </div>
                        @endif
                        @if($proposal->route_name)
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Route</span>
                            <div class="text-sm font-mono text-gray-700">{{ $proposal->route_name }}</div>
                        </div>
                        @endif
                    </div>
                    <div class="space-y-2">
                        @if($proposal->organisator_naam)
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Gebruiker</span>
                            <div class="text-sm text-gray-700">{{ $proposal->organisator_naam }}</div>
                        </div>
                        @endif
                        @if($proposal->toernooi_naam)
                        <div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Toernooi</span>
                            <div class="text-sm text-gray-700">{{ $proposal->toernooi_naam }}</div>
                        </div>
                        @endif
                        @if($proposal->applied_at)
                        <div>
                            <span class="text-xs font-semibold text-green-600 uppercase">Toegepast op</span>
                            <div class="text-sm text-green-700">{{ $proposal->applied_at->format('d-m-Y H:i:s') }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                @if($proposal->apply_error)
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded">
                    <span class="text-xs font-semibold text-red-600 uppercase">Waarom mislukt</span>
                    <div class="text-sm text-red-700 mt-1">{{ $proposal->apply_error }}</div>
                </div>
                @endif

                <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">Claude analyse</span>
                    <pre class="mt-1 text-xs bg-white border rounded p-3 whitespace-pre-wrap max-h-64 overflow-y-auto text-gray-800">{{ $proposal->claude_analysis }}</pre>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            Nog geen AutoFix proposals. Errors worden automatisch geanalyseerd wanneer ze optreden.
        </div>
    @endforelse
</div>

<script @nonce>
function toggleDetail(id) {
    const el = document.getElementById('detail-' + id);
    const arrow = document.getElementById('arrow-' + id);
    el.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
}
</script>
@endsection

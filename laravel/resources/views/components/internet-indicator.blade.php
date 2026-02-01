@props(['size' => 'sm'])

@php
    $sizeClasses = match($size) {
        'lg' => 'w-4 h-4',
        'md' => 'w-3 h-3',
        default => 'w-2 h-2',
    };
@endphp

<div x-data="internetIndicator()" x-init="startMonitoring()" class="inline-flex items-center gap-2">
    {{-- Status Dot --}}
    <div :class="{
            'bg-green-500': status === 'good',
            'bg-orange-500': status === 'poor',
            'bg-red-500': status === 'offline',
            'bg-gray-400': status === 'checking',
            'animate-pulse': status === 'checking'
         }"
         class="{{ $sizeClasses }} rounded-full cursor-pointer"
         @click="showDetails = !showDetails"
         :title="statusLabel">
    </div>

    {{-- Optional: Show label --}}
    @if($size !== 'sm')
    <span x-text="statusLabel"
          :class="{
              'text-green-600': status === 'good',
              'text-orange-600': status === 'poor',
              'text-red-600': status === 'offline',
              'text-gray-500': status === 'checking'
          }"
          class="text-sm font-medium">
    </span>
    @endif

    {{-- Details Popup --}}
    <div x-show="showDetails"
         x-transition
         @click.away="showDetails = false"
         class="absolute top-full right-0 mt-2 bg-white rounded-lg shadow-lg p-4 z-50 min-w-48">
        <div class="text-sm">
            <div class="flex justify-between mb-2">
                <span class="text-gray-600">Status:</span>
                <span x-text="statusLabel" class="font-medium"></span>
            </div>
            <div x-show="latency" class="flex justify-between mb-2">
                <span class="text-gray-600">Latency:</span>
                <span x-text="latency + 'ms'" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
                <span class="text-gray-600">Wachtend:</span>
                <span x-text="queueCount" class="font-medium"></span>
            </div>
            <div class="text-xs text-gray-400 mt-2" x-text="lastCheck"></div>
        </div>
    </div>
</div>

<script>
function internetIndicator() {
    return {
        status: 'checking',
        latency: null,
        queueCount: 0,
        lastCheck: '',
        showDetails: false,
        interval: null,

        get statusLabel() {
            return {
                'good': 'Goed',
                'poor': 'Matig',
                'offline': 'Offline',
                'checking': 'Controleren...'
            }[this.status] || 'Onbekend';
        },

        async checkStatus() {
            try {
                const response = await fetch('/local-server/internet-status');
                const data = await response.json();

                this.status = data.status || 'offline';
                this.latency = data.latency;
                this.queueCount = data.queue_count || 0;
                this.lastCheck = 'Gecontroleerd: ' + new Date().toLocaleTimeString('nl-NL');
            } catch (e) {
                this.status = 'offline';
                this.latency = null;
            }
        },

        startMonitoring() {
            this.checkStatus();
            this.interval = setInterval(() => this.checkStatus(), 15000);
        },

        stopMonitoring() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    };
}
</script>

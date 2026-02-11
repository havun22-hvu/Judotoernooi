@php $targetDate = $block['data']['targetDate'] ?? ''; @endphp
@if($targetDate)
<div class="text-center py-8">
    @if(!empty($block['data']['title']))
        <h3 class="text-xl font-semibold mb-6">{{ e($block['data']['title']) }}</h3>
    @endif
    <div class="flex justify-center gap-4" x-data="{
        target: new Date('{{ $targetDate }}').getTime(),
        days: 0, hours: 0, minutes: 0, seconds: 0,
        interval: null,
        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },
        update() {
            const diff = Math.max(0, this.target - Date.now());
            this.days = Math.floor(diff / 86400000);
            this.hours = Math.floor((diff % 86400000) / 3600000);
            this.minutes = Math.floor((diff % 3600000) / 60000);
            this.seconds = Math.floor((diff % 60000) / 1000);
        }
    }" x-init="start()">
        <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
            <div class="text-3xl font-bold" x-text="days">0</div>
            <div class="text-sm opacity-75">Dagen</div>
        </div>
        <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
            <div class="text-3xl font-bold" x-text="hours">0</div>
            <div class="text-sm opacity-75">Uren</div>
        </div>
        <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
            <div class="text-3xl font-bold" x-text="minutes">0</div>
            <div class="text-sm opacity-75">Min</div>
        </div>
        <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
            <div class="text-3xl font-bold" x-text="seconds">0</div>
            <div class="text-sm opacity-75">Sec</div>
        </div>
    </div>
</div>
@endif

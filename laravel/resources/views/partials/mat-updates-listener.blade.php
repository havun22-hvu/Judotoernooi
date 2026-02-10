{{--
    Real-time mat updates listener via Reverb/Pusher
    Include this partial in views that need live score/beurt updates

    Required variables:
    - $toernooi: The tournament model
    - $matId (optional): Specific mat to listen to, or null for all mats
--}}

@php
    $reverbHost = config('reverb.apps.apps.0.options.host') ?? config('app.url');
    $reverbPort = config('reverb.apps.apps.0.options.port') ?? 443;
    $reverbKey = config('reverb.apps.apps.0.key') ?? env('REVERB_APP_KEY');
    $reverbScheme = config('reverb.apps.apps.0.options.scheme') ?? 'https';
@endphp

<script>
(function() {
    const matUpdateConfig = {
        toernooiId: {{ $toernooi->id }},
        matId: {{ $matId ?? 'null' }},
        reverbHost: '{{ parse_url(config("app.url"), PHP_URL_HOST) }}',
        reverbPort: {{ $reverbPort }},
        reverbKey: '{{ $reverbKey }}',
        reverbScheme: '{{ $reverbScheme }}'
    };

    // Initialize Pusher connection for mat updates
    function initMatUpdates() {
        if (typeof Pusher === 'undefined') {
            console.warn('Pusher not loaded, mat updates disabled');
            return;
        }

        const wsHost = matUpdateConfig.reverbScheme === 'https'
            ? `wss://${matUpdateConfig.reverbHost}`
            : `ws://${matUpdateConfig.reverbHost}`;

        const pusher = new Pusher(matUpdateConfig.reverbKey, {
            wsHost: matUpdateConfig.reverbHost,
            wsPort: matUpdateConfig.reverbPort,
            wssPort: matUpdateConfig.reverbPort,
            forceTLS: matUpdateConfig.reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            cluster: 'mt1'
        });

        // Subscribe to toernooi-wide channel (for publiek/spreker)
        const toernooiChannel = pusher.subscribe(`toernooi.${matUpdateConfig.toernooiId}`);

        // If specific mat, also subscribe to mat channel
        let matChannel = null;
        if (matUpdateConfig.matId) {
            matChannel = pusher.subscribe(`mat.${matUpdateConfig.toernooiId}.${matUpdateConfig.matId}`);
        }

        // Handle incoming mat updates
        function handleMatUpdate(data) {
            console.log('Mat update received:', data);

            // Dispatch custom event for views to handle
            window.dispatchEvent(new CustomEvent('mat-update', {
                detail: data
            }));

            // Type-specific events
            if (data.type === 'score') {
                window.dispatchEvent(new CustomEvent('mat-score-update', { detail: data }));
            } else if (data.type === 'beurt') {
                window.dispatchEvent(new CustomEvent('mat-beurt-update', { detail: data }));
            } else if (data.type === 'poule_klaar') {
                window.dispatchEvent(new CustomEvent('mat-poule-klaar', { detail: data }));
            } else if (data.type === 'bracket') {
                window.dispatchEvent(new CustomEvent('mat-bracket-update', { detail: data }));
            }
        }

        // Bind to mat.update event
        toernooiChannel.bind('mat.update', handleMatUpdate);
        if (matChannel) {
            matChannel.bind('mat.update', handleMatUpdate);
        }

        console.log('Mat updates WebSocket connected for toernooi:', matUpdateConfig.toernooiId);

        // Connection status - dispatch events for UI to track
        pusher.connection.bind('connected', function() {
            console.log('Reverb: Verbonden');
            window.dispatchEvent(new CustomEvent('reverb-connected'));
        });

        pusher.connection.bind('disconnected', function() {
            console.log('Reverb: Verbinding verbroken');
            window.dispatchEvent(new CustomEvent('reverb-disconnected'));
        });

        pusher.connection.bind('error', function(err) {
            console.error('Reverb: Fout', err);
            window.dispatchEvent(new CustomEvent('reverb-disconnected'));
        });
    }

    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMatUpdates);
    } else {
        initMatUpdates();
    }
})();
</script>

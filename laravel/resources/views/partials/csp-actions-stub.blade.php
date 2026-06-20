{{-- CSP-actions queue-stub. MOET vóór @vite staan (in elke <head> die de
     app-bundel laadt). Garandeert dat window.cspActions ALTIJD bestaat, ook als
     de Vite-bundel (script-src 'strict-dynamic' → dynamisch geïnjecteerd) nog
     niet is uitgevoerd wanneer een inline view-script in DOMContentLoaded
     window.cspActions({...}) aanroept. Zonder dit ontstaat een race:
     "window.cspActions is not a function" → dode knoppen. De stub buffert de
     maps; resources/js/csp-actions.js vervangt 'm en flusht de wachtrij. --}}
<script @nonce>
    if (typeof window.cspActions === 'undefined') {
        window.cspActions = function (map) {
            (window.cspActions.q = window.cspActions.q || []).push(map);
        };
    }
</script>

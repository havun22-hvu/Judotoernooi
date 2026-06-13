/**
 * CSP-safe event delegation for migrated inline handlers.
 *
 * The strict CSP (script-src nonce + 'strict-dynamic', no 'unsafe-inline')
 * blocks every vanilla inline event attribute (onclick=, onchange=, ...).
 * Instead of inline handlers, elements carry a `data-action="name"` attribute
 * and the matching behaviour is registered once via `cspActions({...})`.
 *
 * A single set of document-level listeners dispatches by event type + action
 * name, so it works for both server-rendered Blade and JS-rendered innerHTML
 * (delegation needs no element to exist up front). Existing global functions
 * are reused as-is — the registered callback just reads its arguments from the
 * element's data-* attributes and forwards them.
 *
 * Usage (in a nonce'd inline <script> per view, or anywhere):
 *
 *   cspActions({
 *       'naar-zaaloverzicht': (el) => naarZaaloverzichtPoule(+el.dataset.pouleId, el),
 *       'change:update-kruisfinale': (el) => updateKruisfinale(+el.dataset.pouleId, el.value),
 *   });
 *
 * Action keys are "event:name" (event defaults to "click" when omitted). The
 * element only carries the bare name in data-action; the event prefix selects
 * which dispatcher handles it.
 */

const registry = {
    click: {},
    change: {},
    input: {},
    submit: {},
};

const dispatchedEvents = new Set();

function ensureDispatcher(event) {
    if (dispatchedEvents.has(event)) return;
    dispatchedEvents.add(event);
    document.addEventListener(event, (e) => {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        const fn = registry[event] && registry[event][el.dataset.action];
        if (fn) fn(el, e);
    });
}

/**
 * Register a map of action handlers. Keys are "event:name" (or just "name" for
 * click). Values are (element, event) => void callbacks.
 */
export function cspActions(map) {
    for (const [key, fn] of Object.entries(map)) {
        const [event, action] = key.includes(':') ? key.split(':') : ['click', key];
        if (!registry[event]) registry[event] = {};
        registry[event][action] = fn;
        ensureDispatcher(event);
    }
}

// Expose globally so inline (nonce'd) view scripts can call it without imports.
window.cspActions = cspActions;

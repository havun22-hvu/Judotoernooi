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

// Built-in universal actions — cover the most common inline handlers app-wide
// so simple views only need a data-action attribute (no per-view registration).
// A view may override any of these by registering the same name.
const confirmMsg = (el) => (el.dataset.confirm || '').replace(/\\n/g, '\n');
cspActions({
    'print': () => window.print(),
    'reload': () => location.reload(),
    'reload-force': () => location.reload(true),
    'history-back': () => history.back(),
    // Click on a submit button / link guarded by a confirm dialog.
    'confirm': (el, e) => { if (el.dataset.confirm && !confirm(confirmMsg(el))) e.preventDefault(); },
    // Form submit guarded by a confirm dialog.
    'submit:confirm-submit': (el, e) => { if (!confirm(confirmMsg(el))) e.preventDefault(); },
    // Confirm dialog then navigate (was `onclick="if(confirm(..)){location.href=..}"`).
    'confirm-navigate': (el) => { if (confirm(confirmMsg(el))) location.href = el.dataset.href; },
    // Generic DOM one-liners that had no dedicated view function.
    'set-provider': (el) => { const t = document.getElementById('selected-provider'); if (t) t.value = el.dataset.provider; },
    'toggle-detail-log': (el) => document.getElementById('detail-' + el.dataset.logId)?.classList.toggle('hidden'),
    'mark-onvolledig': () => { try { sessionStorage.setItem('toonOnvolledig', 'true'); } catch (e) { /* ignore */ } },
});

// Migration bridge: long-tail views whose handlers call a single global function
// (a `function X(){}` declaration is a window property). Optional chaining makes
// each a no-op on pages where that function isn't present, so one shared
// registration safely covers them all. A view may still override any name.
cspActions({
    'toggle-pw': (el) => (window.togglePassword || window.togglePw)?.(el.dataset.target),
    'toggle-clubs': (el) => window.toggleAlleClubs?.(el.dataset.val === '1'),
    'go-fullscreen': () => window.goFullscreen?.(),
    'toggle-snd-panel': () => window.toggleSndPanel?.(),
    'test-awasete': () => window.testAwaseteSound?.(),
    'select-all': (el) => window.selectAll?.(el.dataset.val === '1'),
    'select-all-mats': (el) => window.selectAllMats?.(el.dataset.val === '1'),
    'meld-probleem': () => window.meldProbleem?.(),
    'approve-login': () => window.approveLogin?.(),
    'copy-all-links': () => window.copyAllLinks?.(),
    'download-weegkaart': () => window.downloadWeegkaart?.(),
    'share-weegkaart': () => window.shareWeegkaart?.(),
    'toggle-detail': (el) => window.toggleDetail?.(+el.dataset.id),
    'reset-crop': () => window.resetCrop?.(),
    'edit-crop': () => window.editCrop?.(),
    'confirm-delete-toernooi': (el) => window.confirmDelete?.(el.dataset.orgSlug, el.dataset.toernooiSlug, el.dataset.toernooiNaam),
    'change:toggle-poule': (el) => window.togglePoule?.(el),
    'change:toggle-mat': (el) => window.toggleMat?.(+el.dataset.matId, el.checked),
    'change:load-image': (el) => window.loadImage?.(el),
    'change:update-betaal': () => window.updateBetaalKnop?.(),
});

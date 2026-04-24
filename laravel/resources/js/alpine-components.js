// Alpine.data() registrations — CSP-build compatible.
//
// Each Alpine inline x-data="{...}" pattern in a Blade view should map
// to one of these named components, so we can eventually swap vanilla
// alpinejs for @alpinejs/csp (which forbids arbitrary in-attribute
// expressions) without breaking views.
//
// Migration tracker: docs/alpine-csp-migration.md

import Alpine from 'alpinejs';

// Generic toggle (open/close) — covers x-data="{ open: false }".
// Optional initial state: x-data="toggle({ open: true })"
Alpine.data('toggle', (initial = {}) => ({
    open: initial.open ?? false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    show()  { this.open = true; },
}));

// Show/hide toggle — covers x-data="{ show: false }" / "{ show: true }".
// Methods: showIt / hideIt / toggleShow.
// Optional flashClass + flashMs: removes a CSS class after delay (used for
// error-blink banners in toernooi/edit to stop the animation after 1.5s).
Alpine.data('showToggle', (initial = {}) => ({
    show: initial.show ?? false,
    showIt()     { this.show = true; },
    hideIt()     { this.show = false; },
    toggleShow() { this.show = !this.show; },
    init() {
        if (initial.flashClass && initial.flashMs) {
            setTimeout(() => {
                if (this.$el) this.$el.classList.remove(initial.flashClass);
            }, initial.flashMs);
        }
    },
}));

// Auto-hide banner — covers x-data="{ show: true }" + x-init="setTimeout(() => show = false, 4000)"
// Use: x-data="autoHide" (default 4000ms) or x-data="autoHide({ ms: 2000 })"
Alpine.data('autoHide', (config = {}) => ({
    show: true,
    init() { setTimeout(() => { this.show = false; }, config.ms ?? 4000); },
}));

// Search filter — covers x-data="{ search: '' }"
Alpine.data('searchFilter', (initial = {}) => ({
    search: initial.search ?? '',
}));

// Editing toggle — covers x-data="{ editing: false }"
Alpine.data('editingToggle', (initial = {}) => ({
    editing: initial.editing ?? false,
    startEdit() { this.editing = true; },
    cancelEdit() { this.editing = false; },
}));

// Dropdown — toggle + outside-click close.
Alpine.data('dropdown', () => ({
    open: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
}));

// Active tab — covers x-data="{ activeTab: '...' }" / "{ tab: '...' }".
// Initial value via config: x-data="tabPanel({ activeTab: 'ranking' })"
Alpine.data('tabPanel', (config = {}) => ({
    activeTab: config.activeTab ?? '',
    setTab(name) { this.activeTab = name; },
    isActive(name) { return this.activeTab === name; },
}));

// Filter + search combo (used in coachkaarten etc.).
// Pass: x-data="filterSearch({ filter: 'alle', search: '' })"
Alpine.data('filterSearch', (config = {}) => ({
    filter: config.filter ?? 'alle',
    search: config.search ?? '',
    setFilter(newFilter) { this.filter = newFilter; },
    onFilterChanged(event) { this.filter = event.detail; },
}));

// Copy ID state — covers x-data="{ copiedId: null, search: '' }".
Alpine.data('copyIdSearch', (config = {}) => ({
    copiedId: config.copiedId ?? null,
    search: config.search ?? '',
    markCopied(id) {
        this.copiedId = id;
        setTimeout(() => { this.copiedId = null; }, 2000);
    },
    copyId(text, id) {
        navigator.clipboard.writeText(text);
        this.markCopied(id);
    },
}));

// Single-value selector — covers x-data="{ openGewicht: null }" /
// "{ activeFavoriet: null }" — generic "what's currently active" tracker.
// Use: x-data="activeSelector" (state on `active`).
Alpine.data('activeSelector', () => ({
    active: null,
    set(id) { this.active = id; },
    clear() { this.active = null; },
    is(id) { return this.active === id; },
    toggle(id) { this.active = this.active === id ? null : id; },
}));

// History + copied — covers x-data="{ showHistory: false, copied: false }".
Alpine.data('historyCopy', () => ({
    showHistory: false,
    copied: false,
    toggleHistory() { this.showHistory = !this.showHistory; },
    markCopied() {
        this.copied = true;
        setTimeout(() => { this.copied = false; }, 2000);
    },
    copyText(text) {
        navigator.clipboard.writeText(text);
        this.markCopied();
    },
}));

// Zoom toggle — for image lightbox style x-data="{ zoomed: false }".
Alpine.data('zoomToggle', () => ({
    zoomed: false,
    toggleZoom() { this.zoomed = !this.zoomed; },
    unzoom() { this.zoomed = false; },
}));

// Lightbox manager — home-page screenshot viewer combining lightbox + zoom.
Alpine.data('lightboxManager', () => ({
    lightbox: null,
    zoomed: false,
    open(src) { this.lightbox = src; this.zoomed = false; },
    close()   { this.lightbox = null; this.zoomed = false; },
    toggleZoom() { this.zoomed = !this.zoomed; },
}));

// Font-size adjustable display (notities tab op publiek-scherm).
// Use: x-data="fontSizer({ size: 18 })"
Alpine.data('fontSizer', (config = {}) => ({
    fontSize: config.size ?? 18,
    bigger()  { this.fontSize = Math.min(48, this.fontSize + 2); },
    smaller() { this.fontSize = Math.max(10, this.fontSize - 2); },
}));

// TV-code input form (used to pair TV via 4-digit code).
Alpine.data('tvCodeInput', () => ({
    tvCode: '',
}));

// User dropdown with nested About-modal (layouts/app.blade.php).
Alpine.data('userDropdown', () => ({
    open: false,
    showAbout: false,
    toggle()      { this.open = !this.open; },
    close()       { this.open = false; },
    openAbout()   { this.showAbout = true; this.open = false; },
    closeAbout()  { this.showAbout = false; },
}));

// URL copy — covers x-data="{ copiedUrl: null }" (club/index).
// Methods: copy(url, marker) stores marker as copiedUrl for 2s, else stores url itself.
Alpine.data('urlCopy', () => ({
    copiedUrl: null,
    copy(url, marker = null) {
        navigator.clipboard.writeText(url);
        this.copiedUrl = marker ?? url;
        setTimeout(() => { this.copiedUrl = null; }, 2000);
    },
}));

// Ranking reveal — covers x-data="{ showRanking: false }" (coach/resultaten).
Alpine.data('rankingToggle', () => ({
    showRanking: false,
    toggleRanking() { this.showRanking = !this.showRanking; },
}));

// QR visibility — covers x-data="{ showQr: false }" (coach/weegkaarten rows).
Alpine.data('qrToggle', () => ({
    showQr: false,
    toggleQr() { this.showQr = !this.showQr; },
    closeQr()  { this.showQr = false; },
}));

// Judoka-row combo — show-flash + open toggle for page-level judoka details.
// `toggle()` method matches the generic naming convention so @click="toggle" works.
Alpine.data('judokaRow', () => ({
    show: true,
    open: false,
    toggle() { this.open = !this.open; },
    close()  { this.open = false; },
}));

// Aanmeldingsformulier (publiek/index toernooi aanmelden).
// Use: x-data="aanmeldForm({ url: '...', csrfToken: '...',
//                            errorMsg: '...', connectionError: '...' })"
Alpine.data('aanmeldForm', (config = {}) => ({
    aanmeldOpen: false,
    aanmeldVerstuurd: false,
    aanmeldError: '',
    aanmeldLoading: false,
    url: config.url ?? '',
    csrfToken: config.csrfToken ?? '',
    errorMsg: config.errorMsg ?? 'Er ging iets mis.',
    connectionError: config.connectionError ?? 'Verbindingsfout. Probeer opnieuw.',
    toggleOpen() { this.aanmeldOpen = !this.aanmeldOpen; },
    openForm()   { this.aanmeldOpen = true; this.aanmeldError = ''; },
    closeForm()  { this.aanmeldOpen = false; },
    async submit() {
        this.aanmeldLoading = true;
        this.aanmeldError = '';
        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    club_naam: this.$refs.clubNaam.value,
                    contact_naam: this.$refs.contactNaam.value,
                    email: this.$refs.email.value,
                    telefoon: this.$refs.telefoon.value,
                }),
            });
            const data = await response.json();
            this.aanmeldLoading = false;
            if (data.success) this.aanmeldVerstuurd = true;
            else this.aanmeldError = data.error || this.errorMsg;
        } catch (e) {
            this.aanmeldLoading = false;
            this.aanmeldError = this.connectionError;
        }
    },
}));

// Warnings banner — covers x-data="{ showWarnings: true }".
Alpine.data('warningsBanner', () => ({
    showWarnings: true,
    dismiss() { this.showWarnings = false; },
}));

// Delete confirm dialog (toernooi/edit) — x-data="{ showConfirm: false, wachtwoord: '' }".
Alpine.data('deleteConfirm', () => ({
    showConfirm: false,
    wachtwoord: '',
    openConfirm()  { this.showConfirm = true; },
    closeConfirm() { this.showConfirm = false; this.wachtwoord = ''; },
}));

// Help collapsible panel — covers x-data="{ showHelp: false }" solo usage
// (distinct from menuHelp which couples with a menuOpen state).
Alpine.data('helpToggle', () => ({
    showHelp: false,
    toggleHelp() { this.showHelp = !this.showHelp; },
    closeHelp()  { this.showHelp = false; },
}));

// Menu + help combo (mat/interface, tv/koppel).
Alpine.data('menuHelp', () => ({
    menuOpen: false,
    showHelp: false,
    toggleMenu() { this.menuOpen = !this.menuOpen; },
    closeMenu()  { this.menuOpen = false; },
    toggleHelp() { this.showHelp = !this.showHelp; },
    closeHelp()  { this.showHelp = false; },
    openHelpFromMenu() { this.menuOpen = false; this.showHelp = true; },
    // Side-effecting helpers used from mat/interface specifically.
    refreshMatInterface() {
        this.menuOpen = false;
        const el = document.getElementById('mat-interface');
        if (el && window.Alpine) window.Alpine.$data(el).refreshAll();
    },
    openPwaSettings() {
        this.menuOpen = false;
        const el = document.getElementById('pwa-settings-modal');
        if (el) el.classList.remove('hidden');
    },
}));

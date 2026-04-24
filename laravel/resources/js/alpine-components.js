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
Alpine.data('showToggle', (initial = {}) => ({
    show: initial.show ?? false,
    showIt()     { this.show = true; },
    hideIt()     { this.show = false; },
    toggleShow() { this.show = !this.show; },
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
}));

// Copy ID state — covers x-data="{ copiedId: null, search: '' }".
Alpine.data('copyIdSearch', (config = {}) => ({
    copiedId: config.copiedId ?? null,
    search: config.search ?? '',
    markCopied(id) {
        this.copiedId = id;
        setTimeout(() => { this.copiedId = null; }, 2000);
    },
}));

// Single-value selector — covers x-data="{ openGewicht: null }" /
// "{ activeFavoriet: null }" / "{ lightbox: null }" — generic "what's
// currently active" tracker.
// Use: x-data="activeSelector" (state on `active`)
Alpine.data('activeSelector', () => ({
    active: null,
    set(id) { this.active = id; },
    clear() { this.active = null; },
    is(id) { return this.active === id; },
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
}));

// Zoom toggle — for image lightbox style x-data="{ zoomed: false }".
Alpine.data('zoomToggle', () => ({
    zoomed: false,
    toggleZoom() { this.zoomed = !this.zoomed; },
    unzoom() { this.zoomed = false; },
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

// Menu + help combo (used in some tool layouts, e.g. tv-koppel page).
Alpine.data('menuHelp', () => ({
    menuOpen: false,
    showHelp: false,
    toggleMenu() { this.menuOpen = !this.menuOpen; },
    closeMenu()  { this.menuOpen = false; },
    toggleHelp() { this.showHelp = !this.showHelp; },
    closeHelp()  { this.showHelp = false; },
}));

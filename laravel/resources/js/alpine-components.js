/**
 * Alpine.data() components — CSP-compatible replacements for inline x-data.
 *
 * The @alpinejs/csp build does not allow inline expressions in x-data,
 * @click, x-show, etc. All component state and methods must be registered
 * here and referenced by name in the views.
 *
 * VP-18 — see HavunCore/docs/audit/verbeterplan-q2-2026.md
 */

export function registerAlpineComponents(Alpine) {
    /**
     * Generic toggle — used for dropdowns, modals, expand/collapse.
     * Replaces: x-data="{ open: false }" + @click="open = !open"
     */
    Alpine.data('toggle', () => ({
        open: false,
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
        show() { this.open = true; },
    }));

    /**
     * Dismissible block — visible by default or invisible, togglable.
     * Replaces: x-data="{ show: true }" or { show: false }.
     * Set initial via data-show-initial="false".
     */
    Alpine.data('dismissible', () => ({
        show: true,
        init() {
            if (this.$el.dataset.showInitial === 'false') this.show = false;
        },
        dismiss() { this.show = false; },
        reveal() { this.show = true; },
        flip() { this.show = !this.show; },
    }));

    /**
     * Modal-with-about: { open: false, showAbout: false } pattern
     * for dialogs that have an optional "about this" sub-panel.
     */
    Alpine.data('modalWithAbout', () => ({
        open: false,
        showAbout: false,
        openModal() { this.open = true; },
        closeModal() { this.open = false; this.showAbout = false; },
        toggleAbout() { this.showAbout = !this.showAbout; },
    }));

    /**
     * Filter switcher — stores a filter string (e.g. 'alle', 'actief').
     * Reads data-initial-filter from host (default 'alle').
     */
    Alpine.data('filterSwitch', () => ({
        filter: 'alle',
        init() {
            this.filter = this.$el.dataset.initialFilter || 'alle';
        },
        setFilter(name) { this.filter = name; },
        is(name) { return this.filter === name; },
    }));

    /**
     * Tab switcher — stores the active tab name.
     * Reads data-initial-tab from host.
     */
    Alpine.data('tabSwitch', () => ({
        tab: '',
        init() {
            this.tab = this.$el.dataset.initialTab || '';
        },
        switchTo(name) { this.tab = name; },
        is(name) { return this.tab === name; },
    }));

    /**
     * Zoomable image — toggle full-size view.
     */
    Alpine.data('zoomable', () => ({
        zoomed: false,
        toggleZoom() { this.zoomed = !this.zoomed; },
        closeZoom() { this.zoomed = false; },
    }));

    /**
     * Lightbox with zoom — combined state for fullscreen image-preview
     * with an optional zoom toggle. Stores the current image URL + zoom flag.
     */
    Alpine.data('lightboxWithZoom', () => ({
        lightbox: null,
        zoomed: false,
        open(url) { this.lightbox = url; this.zoomed = false; },
        close() { this.lightbox = null; this.zoomed = false; },
        toggleZoom() { this.zoomed = !this.zoomed; },
    }));

    /**
     * TV-code entry — keeps a short numeric/alphanumeric code plus submit logic.
     */
    Alpine.data('tvCode', () => ({
        tvCode: '',
        reset() { this.tvCode = ''; },
    }));

    /**
     * Copy-to-clipboard with "Kopieerd!" feedback + history flag.
     */
    Alpine.data('copyHistory', () => ({
        showHistory: false,
        copied: false,
        toggleHistory() { this.showHistory = !this.showHistory; },
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch (_) {
                this.copied = false;
            }
        },
    }));

    /**
     * Ranking panel toggle.
     */
    Alpine.data('rankingPanel', () => ({
        showRanking: false,
        toggleRanking() { this.showRanking = !this.showRanking; },
    }));

    /**
     * QR-code display toggle.
     */
    Alpine.data('qrDisplay', () => ({
        showQr: false,
        toggleQr() { this.showQr = !this.showQr; },
        closeQr() { this.showQr = false; },
    }));

    /**
     * Help / info panel toggle.
     */
    Alpine.data('helpPanel', () => ({
        showHelp: false,
        toggleHelp() { this.showHelp = !this.showHelp; },
        closeHelp() { this.showHelp = false; },
    }));

    /**
     * Warnings banner — visible by default, can be dismissed.
     */
    Alpine.data('warningsBanner', () => ({
        showWarnings: true,
        dismiss() { this.showWarnings = false; },
    }));

    /**
     * Password-guarded confirm modal.
     */
    Alpine.data('confirmWithPassword', () => ({
        showConfirm: false,
        wachtwoord: '',
        openConfirm() { this.showConfirm = true; },
        closeConfirm() { this.showConfirm = false; this.wachtwoord = ''; },
    }));

    /**
     * Toggle with initial state "open" (default true).
     * Reads data-open-initial="false" to start closed.
     */
    Alpine.data('initiallyOpen', () => ({
        open: true,
        init() { if (this.$el.dataset.openInitial === 'false') this.open = false; },
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
    }));

    /**
     * Inline edit state.
     */
    Alpine.data('inlineEdit', () => ({
        editing: false,
        startEdit() { this.editing = true; },
        stopEdit() { this.editing = false; },
    }));

    /**
     * Generic dropdown (named 'dropdown' state).
     */
    Alpine.data('dropdownState', () => ({
        dropdown: false,
        toggleDropdown() { this.dropdown = !this.dropdown; },
        closeDropdown() { this.dropdown = false; },
    }));

    /**
     * Nullable current selection — e.g. active favorite, open gewichtsklasse, lightbox photo.
     * Reads data-state-key to decide the property name ('activeFavoriet', 'openGewicht',
     * 'lightbox', 'copiedUrl', 'copiedId'). Methods: select(id), clear().
     */
    Alpine.data('nullableSelection', () => ({
        current: null,
        stateKey: 'current',
        init() {
            this.stateKey = this.$el.dataset.stateKey || 'current';
            // Surface the selection under its requested key so x-show="activeFavoriet === X" works.
            Object.defineProperty(this, this.stateKey, {
                get: () => this.current,
                set: (v) => { this.current = v; },
                configurable: true,
                enumerable: true,
            });
        },
        select(id) { this.current = id; },
        clear() { this.current = null; },
        is(id) { return this.current === id; },
        toggle(id) { this.current = this.current === id ? null : id; },
    }));

    /**
     * Font-size picker — reads data-initial (default 18).
     */
    Alpine.data('fontSizer', () => ({
        fontSize: 18,
        init() {
            const val = parseInt(this.$el.dataset.initial || '18', 10);
            this.fontSize = Number.isFinite(val) ? val : 18;
        },
        bigger() { this.fontSize = Math.min(this.fontSize + 2, 48); },
        smaller() { this.fontSize = Math.max(this.fontSize - 2, 10); },
    }));

    /**
     * Combined filter + search state.
     */
    Alpine.data('filterSearch', () => ({
        filter: 'alle',
        search: '',
        setFilter(name) { this.filter = name; },
        clearSearch() { this.search = ''; },
        isFilter(name) { return this.filter === name; },
    }));

    /**
     * Show + open combined (e.g. show banner + open details within).
     */
    Alpine.data('showOpen', () => ({
        show: true,
        open: false,
        dismiss() { this.show = false; },
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
    }));

    /**
     * Menu + help combined.
     */
    Alpine.data('menuWithHelp', () => ({
        menuOpen: false,
        showHelp: false,
        toggleMenu() { this.menuOpen = !this.menuOpen; },
        closeMenu() { this.menuOpen = false; },
        toggleHelp() { this.showHelp = !this.showHelp; },
        closeHelp() { this.showHelp = false; },
    }));

    /**
     * Active-tab state with initial value read from data-initial-tab (fallback 'A').
     */
    Alpine.data('activeTab', () => ({
        activeTab: 'A',
        init() {
            this.activeTab = this.$el.dataset.initialTab || 'A';
        },
        setActive(tab) { this.activeTab = tab; },
        isActive(tab) { return this.activeTab === tab; },
    }));
}

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
}

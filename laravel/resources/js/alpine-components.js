/**
 * Alpine.data() components — CSP-compatible replacements for inline x-data.
 *
 * The @alpinejs/csp build does not allow inline expressions in x-data,
 * @click, x-show, etc. All component state and methods must be registered
 * here and referenced by name in the views.
 *
 * VP-18 — see HavunCore/docs/audit/verbeterplan-q2-2026.md
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function intFromDataset(el, key, fallback = 0) {
    const parsed = parseInt(el.dataset[key] ?? '', 10);
    return Number.isNaN(parsed) ? fallback : parsed;
}

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
        get rotateClass() { return this.open ? 'rotate-180' : ''; },
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

    /**
     * Copy-to-clipboard tracker — remembers which key was copied last so the UI
     * can show a green check on that button for a short window (default 2000ms).
     */
    Alpine.data('copyTracker', () => ({
        copiedUrl: null,
        async copyAndMark(text, key) {
            try {
                await navigator.clipboard.writeText(text);
                this.copiedUrl = key;
                setTimeout(() => { this.copiedUrl = null; }, 2000);
            } catch (_) {
                this.copiedUrl = null;
            }
        },
        isCopied(key) { return this.copiedUrl === key; },
    }));

    /**
     * Copy-tracker with a separate filter/search input (used on coach weegkaarten).
     */
    Alpine.data('copyWithSearch', () => ({
        copiedId: null,
        search: '',
        async copyAndMark(text, id) {
            try {
                await navigator.clipboard.writeText(text);
                this.copiedId = id;
                setTimeout(() => { this.copiedId = null; }, 2000);
            } catch (_) {
                this.copiedId = null;
            }
        },
        isCopied(id) { return this.copiedId === id; },
        clearSearch() { this.search = ''; },
    }));

    /**
     * Internet connectivity indicator — polls /local-server/internet-status every 15s.
     * Reads data-labels (JSON) for i18n strings.
     */
    Alpine.data('internetIndicator', () => ({
        status: 'checking',
        latency: null,
        queueCount: 0,
        lastCheck: '',
        showDetails: false,
        interval: null,
        labels: { good: 'Good', poor: 'Poor', offline: 'Offline', checking: 'Checking...', unknown: 'Unknown', checked: 'Checked:' },
        init() {
            try { this.labels = { ...this.labels, ...JSON.parse(this.$el.dataset.labels || '{}') }; } catch (_) {}
            this.checkStatus();
            this.interval = setInterval(() => this.checkStatus(), 15000);
        },
        destroy() { if (this.interval) clearInterval(this.interval); },
        get statusLabel() { return this.labels[this.status] || this.labels.unknown; },
        async checkStatus() {
            try {
                const response = await fetch('/local-server/internet-status');
                const data = await response.json();
                this.status = data.status || 'offline';
                this.latency = data.latency;
                this.queueCount = data.queue_count || 0;
                this.lastCheck = this.labels.checked + ' ' + new Date().toLocaleTimeString('nl-NL');
            } catch (e) {
                this.status = 'offline';
                this.latency = null;
            }
        },
        toggleDetails() { this.showDetails = !this.showDetails; },
        closeDetails() { this.showDetails = false; },
    }));

    /**
     * Offline detector — polls a HEAD endpoint every 10s and listens to
     * online/offline window events. Reads data-ping-url from host.
     */
    Alpine.data('offlineDetector', () => ({
        isOffline: false,
        pingUrl: '',
        interval: null,
        _online: null,
        _offline: null,
        init() {
            this.pingUrl = this.$el.dataset.pingUrl || '';
            this.checkConnection();
            this.interval = setInterval(() => this.checkConnection(), 10000);
            this._online = () => { this.isOffline = false; };
            this._offline = () => { this.isOffline = true; };
            window.addEventListener('online', this._online);
            window.addEventListener('offline', this._offline);
        },
        destroy() {
            if (this.interval) clearInterval(this.interval);
            if (this._online) window.removeEventListener('online', this._online);
            if (this._offline) window.removeEventListener('offline', this._offline);
        },
        async checkConnection() {
            if (!this.pingUrl) return;
            try {
                const response = await fetch(this.pingUrl, { method: 'HEAD', cache: 'no-store' });
                this.isOffline = !response.ok;
            } catch (e) {
                this.isOffline = true;
            }
        },
    }));

    /**
     * Reverb WebSocket server status + controls (start/restart/stop).
     * Reads data-status-url, data-start-url, data-restart-url, data-stop-url
     * + data-labels (JSON with konStatusNietOphalen/foutBijStarten/foutBijHerstarten/foutBijStoppen).
     * CSRF via standard meta tag.
     */
    Alpine.data('reverbStatus', () => ({
        running: false,
        loading: false,
        local: false,
        message: '',
        urls: { status: '', start: '', restart: '', stop: '' },
        labels: { konStatusNietOphalen: 'Status fetch failed', foutBijStarten: 'Start failed', foutBijHerstarten: 'Restart failed', foutBijStoppen: 'Stop failed' },
        init() {
            this.urls = {
                status: this.$el.dataset.statusUrl || '',
                start: this.$el.dataset.startUrl || '',
                restart: this.$el.dataset.restartUrl || '',
                stop: this.$el.dataset.stopUrl || '',
            };
            try { this.labels = { ...this.labels, ...JSON.parse(this.$el.dataset.labels || '{}') }; } catch (_) {}
            this.checkStatus();
        },
        async checkStatus() {
            try {
                const res = await fetch(this.urls.status, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.running = data.running;
                this.local = data.local || false;
            } catch (e) {
                this.message = this.labels.konStatusNietOphalen;
            }
        },
        async _postAction(url, failLabel) {
            this.loading = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.message = data.message;
                await this.checkStatus();
            } catch (e) {
                this.message = this.labels[failLabel];
            }
            this.loading = false;
        },
        start() { return this._postAction(this.urls.start, 'foutBijStarten'); },
        restart() { return this._postAction(this.urls.restart, 'foutBijHerstarten'); },
        stop() { return this._postAction(this.urls.stop, 'foutBijStoppen'); },
    }));

    /**
     * WiFi + internet connection status — pings local-server IP + /ping every 30s.
     * Reads data-local-server-ip from host.
     */
    Alpine.data('verbindingStatus', () => ({
        wifiStatus: 'checking',
        wifiLatency: null,
        internetStatus: 'checking',
        latency: null,
        laatsteCheck: '-',
        localServerIp: '',
        interval: null,
        init() {
            this.localServerIp = this.$el.dataset.localServerIp || '';
            this.checkVerbinding();
            this.interval = setInterval(() => this.checkVerbinding(), 30000);
        },
        destroy() { if (this.interval) clearInterval(this.interval); },
        async checkVerbinding() {
            if (this.localServerIp) {
                const wifiStart = Date.now();
                try {
                    await fetch(`http://${this.localServerIp}:8000/ping`, {
                        method: 'GET', cache: 'no-store', mode: 'no-cors', signal: AbortSignal.timeout(3000),
                    });
                    this.wifiLatency = Date.now() - wifiStart;
                    this.wifiStatus = 'connected';
                } catch (e) {
                    const elapsed = Date.now() - wifiStart;
                    if (elapsed < 2900) {
                        this.wifiLatency = elapsed;
                        this.wifiStatus = 'connected';
                    } else {
                        this.wifiStatus = navigator.onLine ? 'no-server' : 'offline';
                        this.wifiLatency = null;
                    }
                }
            } else {
                this.wifiStatus = navigator.onLine ? 'no-ip' : 'offline';
                this.wifiLatency = null;
            }
            const startTime = Date.now();
            try {
                const response = await fetch('/ping', { method: 'GET', cache: 'no-store', signal: AbortSignal.timeout(5000) });
                this.latency = Date.now() - startTime;
                this.internetStatus = response.ok ? (this.latency > 2000 ? 'slow' : 'connected') : 'offline';
            } catch (e) {
                this.internetStatus = 'offline';
            }
            this.laatsteCheck = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
        },
    }));

    /**
     * Network configuration form — edits local-server + hotspot IPs.
     * Reads data-save-url, data-toernooi-id, data-primary-ip, data-standby-ip,
     * data-hotspot-ip, data-has-own-router, data-save-error-label.
     */
    Alpine.data('netwerkConfig', () => ({
        toernooiId: 0,
        heeftEigenRouter: false,
        primaryIp: '',
        standbyIp: '',
        hotspotIp: '',
        copied: false,
        saveUrl: '',
        saveErrorLabel: 'Error saving network config:',
        init() {
            const d = this.$el.dataset;
            this.toernooiId = parseInt(d.toernooiId || '0', 10);
            this.heeftEigenRouter = d.hasOwnRouter === 'true';
            this.primaryIp = d.primaryIp || '';
            this.standbyIp = d.standbyIp || '';
            this.hotspotIp = d.hotspotIp || '';
            this.saveUrl = d.saveUrl || '';
            this.saveErrorLabel = d.saveErrorLabel || this.saveErrorLabel;
        },
        async saveNetwerkConfig() {
            try {
                await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({
                        heeft_eigen_router: this.heeftEigenRouter === true || this.heeftEigenRouter === '1',
                        local_server_primary_ip: this.primaryIp,
                        local_server_standby_ip: this.standbyIp,
                        hotspot_ip: this.hotspotIp,
                    }),
                });
            } catch (e) {
                console.error(this.saveErrorLabel, e);
            }
        },
        copyToClipboard(text) {
            if (!text) return;
            navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        },
    }));

    /**
     * Noodplan backup download — fetches sync-data endpoint and saves as JSON.
     * Reads data-sync-url, data-toernooi-slug, data-error-label.
     */
    Alpine.data('noodplanBackup', () => ({
        toernooiNaam: '',
        syncUrl: '',
        errorLabel: 'Download error:',
        init() {
            this.toernooiNaam = this.$el.dataset.toernooiSlug || '';
            this.syncUrl = this.$el.dataset.syncUrl || '';
            this.errorLabel = this.$el.dataset.errorLabel || this.errorLabel;
        },
        async downloadBackup() {
            try {
                const response = await fetch(this.syncUrl);
                if (!response.ok) throw new Error('Server error');
                const data = await response.json();
                this._saveAsFile(data, new Date().toISOString().slice(0, 10));
            } catch (e) {
                alert(this.errorLabel + ' ' + e.message);
            }
        },
        _saveAsFile(data, timestamp) {
            const filename = `noodbackup_${this.toernooiNaam}_${timestamp}.json`;
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
    }));

    /**
     * Noodplan local-server control panel — edits IP, tracks uitslagen, imports/exports JSON backup.
     * Reads data-toernooi-id, data-toernooi-slug, data-primary-ip, data-save-url, data-sync-url
     * + data-labels (ongeldigIpFormaat / foutBijOpslaan / gekopieerd / geenDataBeschikbaar /
     * ditBackupVanAnder / backupGeladen / ongeldigJsonBestand).
     */
    Alpine.data('noodplanLocalServer', () => ({
        toernooiId: 0,
        toernooiNaam: '',
        primaryIp: '',
        uitslagCount: 0,
        laatsteSync: null,
        editingIp: false,
        newIp: '',
        saveUrl: '',
        syncUrl: '',
        labels: {},
        interval: null,
        init() {
            const d = this.$el.dataset;
            this.toernooiId = parseInt(d.toernooiId || '0', 10);
            this.toernooiNaam = d.toernooiSlug || '';
            this.primaryIp = d.primaryIp || '';
            this.saveUrl = d.saveUrl || '';
            this.syncUrl = d.syncUrl || '';
            try { this.labels = JSON.parse(d.labels || '{}'); } catch (_) {}
            this.loadFromStorage();
            this.interval = setInterval(() => this.loadFromStorage(), 1000);
        },
        destroy() { if (this.interval) clearInterval(this.interval); },
        startEditIp() {
            this.newIp = this.primaryIp || '';
            this.editingIp = true;
            this.$nextTick(() => this.$refs.ipInput?.focus());
        },
        async saveIp() {
            const ip = this.newIp.trim();
            if (!ip) { this.editingIp = false; return; }
            const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
            if (!ipRegex.test(ip)) { alert(this.labels.ongeldigIpFormaat); return; }
            try {
                await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ local_server_primary_ip: ip }),
                });
                this.primaryIp = ip;
                this.editingIp = false;
            } catch (e) {
                alert(this.labels.foutBijOpslaan + ' ' + e.message);
            }
        },
        copyUrl() {
            const url = 'http://' + (this.primaryIp || 'laptop-ip') + ':8000';
            navigator.clipboard.writeText(url);
            alert(this.labels.gekopieerd + ' ' + url);
        },
        loadFromStorage() {
            const countKey = `noodplan_${this.toernooiId}_count`;
            const syncKey = `noodplan_${this.toernooiId}_laatste_sync`;
            const count = localStorage.getItem(countKey);
            this.uitslagCount = count ? parseInt(count) : 0;
            const sync = localStorage.getItem(syncKey);
            if (sync) {
                const date = new Date(sync);
                this.laatsteSync = date.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        },
        async downloadBackup() {
            try {
                const response = await fetch(this.syncUrl);
                if (!response.ok) throw new Error('Server error');
                const data = await response.json();
                this._saveAsFile(data);
            } catch (e) {
                const storageKey = `noodplan_${this.toernooiId}_poules`;
                const cached = localStorage.getItem(storageKey);
                if (cached) this._saveAsFile(JSON.parse(cached));
                else alert(this.labels.geenDataBeschikbaar);
            }
        },
        _saveAsFile(data) {
            const timestamp = new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-');
            const filename = `backup_${this.toernooiNaam}_${timestamp}.json`;
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        loadJsonBackup(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const data = JSON.parse(e.target.result);
                    if (data.toernooi_id && data.toernooi_id !== this.toernooiId) {
                        if (!confirm(this.labels.ditBackupVanAnder.replace(':id', data.toernooi_id))) return;
                    }
                    const storageKey = `noodplan_${this.toernooiId}_poules`;
                    const syncKey = `noodplan_${this.toernooiId}_laatste_sync`;
                    const countKey = `noodplan_${this.toernooiId}_count`;
                    localStorage.setItem(storageKey, JSON.stringify(data));
                    localStorage.setItem(syncKey, new Date().toISOString());
                    let count = 0;
                    if (data.poules) {
                        data.poules.forEach((p) => {
                            if (p.wedstrijden) p.wedstrijden.forEach((w) => { if (w.is_gespeeld) count++; });
                        });
                    }
                    localStorage.setItem(countKey, count.toString());
                    this.loadFromStorage();
                    alert(this.labels.backupGeladen.replace(':poules', data.poules?.length || 0).replace(':uitslagen', count));
                } catch (err) {
                    alert(this.labels.ongeldigJsonBestand + ' ' + err.message);
                }
            };
            reader.readAsText(file);
            event.target.value = '';
        },
    }));

    Alpine.data('helpPage', () => ({
        searchQuery: '',
        filteredCount: 0,
        sections: [],
        init() {
            this.sections = [...document.querySelectorAll('.help-section')].map((el) => ({
                el,
                text: el.textContent.toLowerCase(),
                keywords: (el.dataset.keywords || '').toLowerCase(),
            }));
            this.filteredCount = this.sections.length;
        },
        filterContent() {
            const query = this.searchQuery.toLowerCase().trim();
            let count = 0;
            this.sections.forEach(({ el, text, keywords }) => {
                const matches = !query || text.includes(query) || keywords.includes(query);
                el.classList.toggle('hidden', !matches);
                if (matches) count++;
            });
            this.filteredCount = count;
        },
        clear() {
            this.searchQuery = '';
            this.filterContent();
        },
        get showQuickstart() {
            return !this.searchQuery || 'quickstart snel starten begin'.includes(this.searchQuery.toLowerCase());
        },
        get noResults() {
            return this.searchQuery && this.filteredCount === 0;
        },
    }));

    /**
     * Poule-select: dispatches noodplan-print:refresh so noodplanPrint can recount.
     * Suppressed during bulk operations (noodplanPrint.selectAll) to avoid N² dispatches.
     */
    Alpine.data('pouleSelect', () => ({
        printInclude: true,
        init() {
            this.$watch('printInclude', (value) => {
                this.$el.classList.toggle('print-exclude', !value);
                this.$el.classList.toggle('opacity-50', !value);
                if (window.__noodplanPrintBulk) return;
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('noodplan-print:refresh'));
                });
            });
        },
    }));

    Alpine.data('noodplanPrint', () => ({
        total: 0,
        selected: 0,
        init() {
            this.total = intFromDataset(this.$el, 'totalPoules');
            window.addEventListener('noodplan-print:refresh', () => this.updateCounter());
            this.$nextTick(() => this.updateCounter());
        },
        selectAll(checked) {
            window.__noodplanPrintBulk = true;
            document.querySelectorAll('.poule-page input[type="checkbox"]').forEach((checkbox) => {
                checkbox.checked = checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
            window.__noodplanPrintBulk = false;
            this.$nextTick(() => this.updateCounter());
        },
        updateCounter() {
            this.selected = document.querySelectorAll('.poule-page:not(.print-exclude)').length;
        },
        print() {
            window.print();
        },
    }));

    /**
     * Server-pakket sync status — polls localStorage for a per-toernooi sync timestamp
     * every 5s. connected = <2min ago, stale = older, none = never.
     * Reads data-toernooi-id from host.
     */
    Alpine.data('serverPakketStatus', () => ({
        syncStatus: 'none',
        laatsteSync: '',
        toernooiId: null,
        interval: null,
        init() {
            this.toernooiId = parseInt(this.$el.dataset.toernooiId || '0', 10);
            this.checkSync();
            this.interval = setInterval(() => this.checkSync(), 5000);
        },
        destroy() { if (this.interval) clearInterval(this.interval); },
        checkSync() {
            const syncKey = `noodplan_${this.toernooiId}_laatste_sync`;
            const sync = localStorage.getItem(syncKey);
            if (!sync) {
                this.syncStatus = 'none';
                return;
            }
            const syncDate = new Date(sync);
            const diffMs = Date.now() - syncDate.getTime();
            this.laatsteSync = syncDate.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.syncStatus = diffMs < 120000 ? 'connected' : 'stale';
        },
    }));
}

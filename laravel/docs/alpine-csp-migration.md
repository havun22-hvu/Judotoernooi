# Alpine.js CSP migration — JudoToernooi

> Doel: Mozilla Observatory `unsafe-eval` penalty (-10) wegwerken door
> over te stappen naar `@alpinejs/csp` build. Vereist dat alle Alpine
> directives via `Alpine.data()` registraties lopen i.p.v. inline
> `x-data="{...}"` of inline expressions zoals `@click="open = !open"`.

## Strategie

1. ✅ Maak shared utility components in `resources/js/alpine-components.js`
2. ⏳ Refactor views: vervang inline `x-data` met named componenten
3. ⏳ Vervang inline expressions met method references
4. ⏳ Migreer in-view `function xyz()` (1 stuks: internetIndicator)
5. ⏳ Switch naar `@alpinejs/csp` package + verwijder `'unsafe-eval'` uit CSP

## Status (24-04-2026 nacht)

### ✅ Shared lib + 17 views migrated

`resources/js/alpine-components.js` — 23 componenten:
toggle, showToggle, autoHide, searchFilter, editingToggle, dropdown,
tabPanel, filterSearch, copyIdSearch, activeSelector, historyCopy,
zoomToggle, fontSizer, tvCodeInput, userDropdown, menuHelp, urlCopy,
rankingToggle, qrToggle, judokaRow, aanmeldForm, warningsBanner,
deleteConfirm.

**Views klaar (17):**
- `layouts/app.blade.php`
- `organisator/dashboard.blade.php`
- `organisator/auth/login.blade.php`
- `partials/coach-locale-switcher.blade.php`
- `pages/club/index.blade.php`
- `pages/wedstrijddag/poules.blade.php`
- `pages/wedstrijddag/partials/poule-card.blade.php`
- `pages/toernooi/edit.blade.php` (alleen `{ open: false }` patroon — meer x-data nog te doen)
- `pages/poule/index.blade.php`
- `pages/noodplan/index.blade.php`
- `pages/judoka/index.blade.php`
- `pages/home.blade.php` (alleen `{ open: false }` patroon — meer x-data nog te doen)
- `pages/coach/judokas.blade.php` (alleen `{ open: false }` patroon — meer x-data nog te doen)
- `pages/blok/zaaloverzicht.blade.php` (alleen `{ open: false }` patroon — meer x-data nog te doen)

### ⏳ Resterend (~25 views met andere x-data patronen)

Per views nog te refactoren met de NEW utility components:

**Eenvoudig (recurring patroon, < 5 regels per view)**
- `pages/coach/coachkaarten.blade.php` — historyCopy
- `pages/coach/resultaten.blade.php` — rankingToggle
- `pages/coach/weegkaarten.blade.php` — copyIdSearch + qrToggle
- `pages/resultaten/organisator.blade.php` — tabPanel
- `pages/toernooi/index.blade.php` — tabPanel
- `pages/mat/interface.blade.php` — menuHelp

**Middel (gebruikt activeSelector / multi-state)**
- `pages/home.blade.php` — activeSelector (lightbox) + zoomToggle
- `pages/publiek/index.blade.php` — meerdere: aanmeldForm,
  activeSelector (openGewicht + activeFavoriet), warningsBanner

**Complex (custom one-off state)**
- `pages/toernooi/edit.blade.php` — `{ activeTab }`, `{ modus }`,
  `{ value }`, `{ categorieType }`, `{ showConfirm, wachtwoord }`,
  `{ showWarnings }`, multiple `{ show: true }` (autoHide)
- `pages/coach/judokas.blade.php` — multiple `{ filter: 'alle' }`,
  `{ filter, search }`, `{ editing }`
- `pages/blok/zaaloverzicht.blade.php` — multi-line complex `{ ... }`
- `pages/judoka/index.blade.php` — `{ show: true, open: false }`

**Named function (1 stuks)**
- `components/internet-indicator.blade.php` — `function internetIndicator()`
  → naar Alpine.data() in shared lib

### ⏳ CSP build switch — wacht op 100% migratie

Final stap (na alle views + internetIndicator):
```js
// resources/js/app.js
- import Alpine from 'alpinejs';
+ import Alpine from '@alpinejs/csp';
```
```php
// app/Http/Middleware/SecurityHeaders.php
-script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' ...
+script-src 'self' 'nonce-{$nonce}' ...
```

Verifieer: Mozilla Observatory rescan = +10 punten op judotournament.org
(geen `unsafe-eval` meer; A → A+).

### Live op staging.judotournament.org

24-04-2026 nacht: alle wijzigingen staan op staging. Test flows:
- Top-balk language switcher + user dropdown + About modal
- Organisator-dashboard zelfde dropdowns + About modal
- Club-index club-uitnodigen page + URL/PIN copy buttons
- Diverse `{ open: false }` toggles in toernooi/poule/judoka/wedstrijddag
  pagina's (info-iconen, expand/collapse blokken)

Productie deploy ná visuele bevestiging.

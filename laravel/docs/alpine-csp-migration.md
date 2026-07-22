# Alpine.js CSP migration — JudoToernooi

> Doel: Mozilla Observatory `unsafe-eval` penalty (-10) wegwerken door
> over te stappen naar `@alpinejs/csp` build. Vereist dat alle Alpine
> directives via `Alpine.data()` registraties lopen i.p.v. inline
> `x-data="{...}"` of inline expressions zoals `@click="open = !open"`.

## ⚠️ De assignment-regel: `foo = x` mag, `foo.bar = x` niet (16-07-2026)

Dit is de scherpste regel van de CSP-build en de bron van meerdere bugs. In
`node_modules/@alpinejs/csp/dist/module.esm.js` (`case "AssignmentExpression"`):

```js
if (node.left.type === "Identifier") {
  scope[node.left.name] = value;    // ✅ `open = false` werkt
} else if (node.left.type === "MemberExpression") {
  throw new Error("Property assignments are prohibited in the CSP build");  // ❌ `form.naam = x`
}
```

Drie gevallen, met verschillend gedrag:

| Expressie | Gedrag |
|---|---|
| `open = false` — property op de **eigen** component | ✅ Werkt. |
| `open = false` — property op een **ancestor**-component | ⚠️ **Stil fout.** De assignment schrijft naar `scope[name]` = de *eigen* scope, dus de ancestor-property verandert nooit. Geen error. Verplaats naar een methode op de component die de property bezit. |
| `form.naam = x` — elk pad met een punt | ❌ **Harde error** `Property assignments are prohibited`. |

### Compound handler met een call → gebruik één wrapper (22-07-2026)

Een event-handler die een **methode-call met `;` chained** — `@change="updateJP(...); saveScore(...)"` —
wordt door de CSP-evaluator **stil niet uitgevoerd**: géén statement draait, géén console-error.
Vervang door één wrapper-methode op de component:

```blade
@change="updateJpEnSla(w, judoka.id, $event.target.value, poule)"
```
```js
updateJpEnSla(w, id, v, poule) { this.updateJP(w, id, v); this.saveScore(w, poule); },
```

Guard: `AlpineCspBindingTest::no_event_handler_chains_a_method_call_with_a_semicolon` (statische
blade-scan). Dit doodde stil de poule-scoring (auto WP/JP + totalen) op staging (22-07-2026).

### `x-model` op een genest pad is dus altijd stuk

`x-model` bouwt intern letterlijk de string `<expressie> = __placeholder` en evalueert die.
Bij `x-model="form.naam"` wordt dat `form.naam = __placeholder` → MemberExpression → **throw**.
Werkt lokaal (CSP staat alleen aan buiten `local`), breekt op staging/prod.

**Fix — geef `x-model` een getter/setter-paar.** Alpine's `x-model` checkt eerst
`isGetterSetter(result)`, en gebruikt dan `result.set(value)` zonder de assignment-string ooit
te parsen (die wordt lazy geëvalueerd, dus hij ontploft niet):

```js
// In de Alpine.data() component:
formModel(veld) {
    return {
        get: () => this.form[veld],
        set: (waarde) => { this.form[veld] = waarde; },
    };
},
```
```blade
<input x-model="formModel('naam')">   {{-- i.p.v. x-model="form.naam" --}}
```

De datastructuur blijft intact, dus `JSON.stringify(this.form)` bij submit blijft werken.
Toegepast op `device-toegangen` (`nvModel`), `clubs/index` (`editModel`),
`stambestand/index` (`formModel`) en `toernooi/mobiel` (`njModel`).

**Guard:** `grep -rn 'x-model="[a-zA-Z_$]*\.' resources/views/` moet leeg blijven.

## ⚠️ cspActions load-order race (fix 20-06-2026)

**Symptoom:** intermitterend `window.cspActions is not a function` op admin-
pagina's (judoka, blok, soms dashboard) → knoppen dood. Niet-deterministisch.

**Oorzaak:** CSP gebruikt `script-src 'strict-dynamic'` (SecurityHeaders.php).
Daardoor injecteert Vite de app-bundel dynamisch en is uitvoering **niet
gegarandeerd vóór `DOMContentLoaded`**. De 15 views die hun knoppen op DCL
registreren via `window.cspActions({...})` crashen als de bundel (die
`window.cspActions` zet in `csp-actions.js`) die race verliest.

**Fix:** queue-stub. `resources/views/partials/csp-actions-stub.blade.php`
definieert `window.cspActions` als buffer en staat **vóór `@vite`** in elke
`<head>` die de bundel laadt. `csp-actions.js` vangt de buffer op, vervangt de
stub door de echte dispatcher, flusht de wachtrij (ná de built-ins, zodat view-
registraties built-ins blijven overschrijven) en zet `window.cspActions.__ready`.

**LET OP bij nieuwe standalone pagina's:** elke view met een **eigen `<head>` +
`@vite`** (dus NIET `@extends('layouts.app')`) moet
`@include('partials.csp-actions-stub')` direct vóór `@vite` hebben. Gedekt:
layouts/app + dashboard, setup-pin, coach-kaart/activeer, dojo/scanner,
mat/interface, weging/interface, spreker/interface. Regressie-guard:
`e2e/csp-race.auth.spec.ts` (vertraagt de bundel 800ms → forceert de race).

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

> De build-switch naar `@alpinejs/csp` is uitgevoerd; de app draait er productie-breed op.
> Nieuwe interactie-bugs vallen daarom onder de regels bovenaan dit doc + de guards in
> `AlpineCspBindingTest`.


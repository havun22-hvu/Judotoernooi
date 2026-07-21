---
title: Publieke PWA (toeschouwers)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Publieke PWA (toeschouwers)

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Publieke PWA (Toeschouwers)

**Route:** `/publiek/{slug}`
**View:** `resources/views/pages/publiek/index.blade.php`
**Controller:** `PubliekController`

### Toegang
- Openbaar, geen authenticatie nodig
- PWA installeerbaar op homescreen

### Tabs

| Tab | Beschikbaarheid | Inhoud |
|-----|-----------------|--------|
| **Info** | Altijd | Toernooi info, tijdschema, QR-code |
| **Deelnemers** | Altijd | Per leeftijd/gewicht, ster voor favoriet |
| **Favorieten** | Altijd | Geselecteerde judoka's + hun poules |
| **Live Matten** | Wedstrijddag | Per mat wie speelt/klaar maakt |
| **Uitslagen** | Na afloop | Eindstanden per poule |

### Deelnemers Tab - Categorie Weergave

De deelnemers worden gegroepeerd op basis van de **toernooi-instellingen** (`$toernooi->gewichtsklassen`), NIET op basis van het `gewichtsklasse` veld in de judoka database.

**Logica per categorie:**

| `max_kg_verschil` | Type | Weergave |
|-------------------|------|----------|
| `0` (of leeg) | **Vaste klassen** | Buttons per gewichtsklasse (-24kg, -27kg, etc.) |
| `> 0` | **Dynamisch** | Alle judoka's in één lijst gesorteerd op leeftijd + gewicht |

**Controller bepaalt:**
1. Lees `$toernooi->gewichtsklassen` (categorie-instellingen)
2. Per leeftijdscategorie: check `max_kg_verschil`
3. Als `max_kg_verschil > 0` → dynamische indeling → geen gewichtsklasse-groepering
4. Als `max_kg_verschil == 0` → vaste klassen → groepeer op `gewichten` array uit config

**View toont:**
- **Dynamische categorie**: Alle judoka's direct zichtbaar, gesorteerd op leeftijd + gewicht.
  Géén gewichtsklasse-groepering — categorie-label (leeftijdsklasse) staat bovenaan, dan de
  lijst met judoka-namen, leeftijd en gewicht. Dit is by design (variabele indeling: er zíjn
  geen vaste gewichtsklassen).
- **Vaste categorie**: Knoppen per gewichtsklasse (uit config), klik voor judoka lijst.

**Inklap-gedrag per leeftijdsklasse (`resources/views/pages/publiek/index.blade.php:582`).**
Elke leeftijdsklasse zit in een `<div x-data="{ collapsed: false }">`. De header is een knop
die `collapsed` toggelt; de content-`<div>` staat `x-show="!collapsed" x-collapse`. Bij open
verschijnt de dynamische-lijst of gewichtsklasse-knoppen; bij dicht alleen de header met het
totaal-aantal judoka's. Chevron draait naar rechts als dicht, naar beneden als open.
`collapsed` is een Identifier op de eigen component-scope → CSP-safe.

**Open bug (21-07): H-15 / D-15 klappen niet uit** op het Generale-toernooi. Vast klasse-
type (`max_kg_verschil: 0`, `gewichten: ['-55','-60',...]`), zou de gewichtsklasse-knoppen
moeten tonen bij een klik op de header. Klap-gedrag is by design (`collapsed` toggle op de
knop), maar visueel blijft de content dicht. Diagnose loopt — zie `.claude/plan-deelnemers-tab.md`.

**Voorbeeld configuratie:**
```php
// Toernooi instelling voor "jeugd" categorie
'jeugd' => [
    'label' => 'Jeugd',
    'max_leeftijd' => 11,
    'max_kg_verschil' => 3,  // > 0 = dynamisch
    'gewichten' => [],        // Leeg bij dynamisch
]

// Toernooi instelling voor "pupillen" categorie
'pupillen' => [
    'label' => 'Pupillen',
    'max_leeftijd' => 9,
    'max_kg_verschil' => 0,   // 0 = vaste klassen
    'gewichten' => ['-24', '-27', '-30', '+30'],
]
```

### Live Matten Tab - Groen/Geel Weergave

> **Uitgebreide documentatie:** Zie `MAT-WEDSTRIJD-SELECTIE.md` voor volledige technische details.

Per mat worden getoond:
1. **Groen (speelt nu)**: Wedstrijd met beide judoka's
2. **Geel (klaar maken)**: Volgende wedstrijd met beide judoka's

```
┌─────────────────────────────────┐
│ MAT 1                    [LIVE] │
│ Poule #5 - Jeugd -24            │
├─────────────────────────────────┤
│ 🥋 SPEELT NU                    │
│ Jan (wit) vs Piet (blauw)       │
│ Judo Hoorn vs Judo Alkmaar      │
├─────────────────────────────────┤
│ ⏳ KLAAR MAKEN                  │
│ Karel (wit) vs Ahmed (blauw)    │
│ Judo Enkhuizen vs Judo Den Helder│
└─────────────────────────────────┘
```

**Data:** Komt van `actieve_wedstrijd_id` (groen) en `volgende_wedstrijd_id` (geel) op de **mat** (niet poule).

**Belangrijk:** Er is maar 1 groen en 1 geel per mat, ongeacht het aantal poules op die mat.

### Live Matten - Weergavemodi (Overzicht/Detail)

Twee weergavemodi voor de Live Matten tab:

| Modus | Gebruik | Layout |
|-------|---------|--------|
| **Overzicht** (standaard) | LCD/PC scherm aan de kant, alle matten in zicht | Desktop: grid 2 kolommen, Mobiel: onder elkaar |
| **Detail** | Bij de mat zelf, 1 mat groot | Mat vult volledige breedte, grotere tekst |

**Wisselen tussen modi:**
- **Vergroten**: Klik op vierkantje-icoon (expand) rechtsboven in mat header → detail modus
- **Verkleinen**: Klik op dubbel-vierkantje-icoon (collapse) rechtsboven → terug naar overzicht

**Gedrag:**
- Desktop overzicht: `grid-cols-2` (2 rijen als >2 matten, alles zichtbaar)
- Mobiel/PWA overzicht: `grid-cols-1` (onder elkaar)
- Detail: geselecteerde mat fullwidth, rest verborgen
- State (`selectedMatId`) in Alpine.js, `null` = overzicht
- Bij Reverb updates blijft geselecteerde mat behouden

### Favorieten Tab - Groen/Geel Weergave

In de poule van je favoriet worden groen/geel spelers **bovenaan** getoond:

```
┌─────────────────────────────────┐
│ P#5 Jeugd -24    Mat 1 | Blok 1 │
├─────────────────────────────────┤
│ 🥋 Jan (speelt nu)    8WP 34JP  │  ← Groen, altijd bovenaan
│ ⏳ Karel (klaar maken) 6WP 25JP │  ← Geel, daarna
├─────────────────────────────────┤
│ 1. Piet ★              4WP 12JP │  ← Je favoriet
│ 2. Ahmed               2WP  5JP │
│ 3. ...                          │
└─────────────────────────────────┘
```

**Alerts:**
- Groene banner: "🥋 NU! [Naam] is aan het vechten!"
- Gele banner: "⚡ Maak je klaar! [Naam] is bijna aan de beurt"

### Naam-tabs kleuren naar de beurt

De naam-tabs onder "mijn favorieten" nemen de **beurtkleur** aan als achtergrond, zodat je in één
oogopslag ziet of je favoriet moet spelen / klaar staan / klaar maken:

| Beurt | Kleur | Bron-flag |
|-------|-------|-----------|
| Speelt nu | groen | `is_aan_de_beurt` |
| Klaar staan | geel | `is_volgende` |
| Klaar maken | blauw | `is_gereedmaken` |
| Geen beurt | grijs | — |

De **geselecteerde** tab krijgt een **oranje ring** (`ring-2 ring-orange-500`) + vet — oranje omdat
groen/geel/blauw al beurtkleuren zijn, zo blijven beurt én selectie beide zichtbaar. De beurt komt uit
de component-methode `favorietBeurt(id)` (CSP-safe; houdt de `.some()`-logica uit de blade).

### Eliminatie-poules in de favorieten-tab

Een eliminatie-poule heeft geen ranglijst maar een bracket, dus de round-robin-kaart (positie/WP/JP)
past er niet op. Voor `poule.type === 'eliminatie'` toont de kaart daarom de **status van dat moment**
voor de actieve favoriet (`eliminatie.status`):

| status | Weergave |
|--------|----------|
| `komt` | Badge `A · 1/4 finale` (groep + rondenaam) + `Mat X`, daaronder **het veld** dat nog actief is in die ronde (favoriet vet, oranje highlight) |
| `afgevallen` | `Afgevallen — B · 1/8 finale` (groep + ronde van de laatste verloren partij) |
| `medaille` | 🏅 eindplaats — 1e/2e/3e, `3e (gedeeld)` bij meerdere bronzen |

**Geen losse tegenstander bij `komt`.** In een bracket is de tegenstander vaak nog onbekend (de
vorige ronde is niet gespeeld) en één naam tonen suggereert zekerheid die er niet is — inconsistent
met de mat-weergave. In plaats daarvan toont `eliminatie.veld` **alle judoka's die nog in die ronde
actief zijn** (per partij: niet gespeeld → beide judoka's, gespeeld → alleen de winnaar). De favoriet
staat vet. De lijst kan lang zijn (bv. ~16 bij een 1/8); de pagina scrollt gewoon door (geen vaste
hoogte op `body`/`main`).

De **groep** (`A`/`B`) komt uit de ronde-key: prefix `b_` → B-groep, anders A
(`BracketLayoutService::rondeGroep()`). Rondenamen via `BracketLayoutService::rondeNaam()`.

`PubliekController@favorieten` verrijkt elke favoriet-judoka in een eliminatie-poule met een
`eliminatie`-object (`bouwEliminatieInfo()`); round-robin poules krijgen `eliminatie: null` en houden
de ranglijst-kaart. De statusbanners (blauw/geel/groen) en push-meldingen draaien op de per-judoka
flags en werken voor beide types ongewijzigd. De blade leest de info via de component-methode
`favorietEliminatie(poule)` (CSP-safe: geen optional chaining in Alpine-expressies).

> **Render-robuustheid:** de actieve poule-kaart wordt gekozen via component-methodes
> (`actievePoules()` / `kiesActieveFavoriet()`), niet via een losse `$watch`-timing. `activeFavoriet`
> wordt imperatief gezet ná het laden en valt terug op de eerste favoriet mét poule — zo kan de kaart
> nooit stil leeg blijven zolang er data is.

### Push-meldingen bij favorieten

Bij favorieten stuurt de app een browsermelding zodra een favoriet **klaar moet staan** (geel) of
**aan de beurt** is (groen). `checkAndNotify()` vergelijkt elke Reverb-update met `notifiedState`
(in localStorage, zodat een herladen niet dubbel meldt) en roept `toonMelding()` aan.

**Melden gaat via de service worker, niet via `new Notification()`.** Android Chrome verbiedt de
Notification-constructor (`Illegal constructor`) en eist `registration.showNotification()`. De
`toonMelding()`-helper haalt daarom `navigator.serviceWorker.ready` op en gebruikt
`showNotification`; alleen desktop zonder service worker valt terug op de constructor. `sw.js` heeft
een `notificationclick`-handler die de open tab focust (of `/` opent).

> **Was kapot (juli 2026):** de code deed `new Notification(...)` in een `try/catch` die de fout stil
> in `console.log` gooide → op Android verscheen **nooit** een melding, zonder zichtbaar spoor.

**Diagnose zonder console.** Een tablet heeft geen DevTools. De "Aanzetten"-knop toont daarom bij
elke afloop een `notificatieStatus`-regel onder de banner: geblokkeerd (permission `denied`), geen
toestemming (`default`), browser zonder ondersteuning (bijv. een in-app webview), of een fout bij
het tonen. Blijft de banner staan zonder tekst? Dan werd de knop niet aangeroepen (Alpine/CSP).

### Real-time Updates via Reverb

De publiek app ontvangt real-time updates via WebSockets (Laravel Reverb):

| Event | Actie |
|-------|-------|
| `mat-score-update` | Herlaadt matten + favorieten data |
| `mat-beurt-update` | Herlaadt matten + favorieten data |
| `mat-poule-klaar` | Herlaadt matten + favorieten data |

> **Zie:** `CHAT.md` voor volledige Reverb documentatie

De events dragen **alleen wedstrijd-ID's**, geen namen (`MatUpdate` → `MatUitslagController.php:482`).
De browser gebruikt het event als signaal en haalt de namen op via `PubliekController@favorieten`.
Dat namen en wedstrijdgegevens sowieso publiek mogen: zie `6-INTERNAL/GEGEVENS-EN-PRIVACY.md`.

### Geen polling meer
Polling is volledig verwijderd (feb 2026). Reverb WebSocket events zijn de enige
bron van updates. Handmatige refresh-knop beschikbaar als fallback.

### Puntenberekening Favorieten
WP/JP worden **live berekend** uit wedstrijden in `PubliekController@favorieten`:
- WP: win=2, gelijk=1, verlies=0
- JP: som van scores per judoka

**Let op:** NIET uit `poule_judoka.punten` pivot (die gebruikt een andere formule).

### Bestanden
- `PubliekController@index` - Hoofd view
- `PubliekController@favorieten` - AJAX endpoint voor favorieten poules (berekent WP/JP)
- `resources/views/pages/publiek/index.blade.php` - Alpine.js SPA

---


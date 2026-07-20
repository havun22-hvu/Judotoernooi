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
- **Dynamische categorie**: Alle judoka's direct zichtbaar, gesorteerd op leeftijd + gewicht
- **Vaste categorie**: Knoppen per gewichtsklasse (uit config), klik voor judoka lijst

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

### Eliminatie-poules in de favorieten-tab

Een eliminatie-poule heeft geen ranglijst maar een bracket, dus de round-robin-kaart (positie/WP/JP)
past er niet op. Voor `poule.type === 'eliminatie'` toont de kaart daarom een compacte variant voor
de **actieve favoriet**:

- **Eindplaats** (🏅) als de favoriet een medaille heeft — alleen 1e/2e/3e, en `3e (gedeeld)` bij
  meerdere bronzen. Uitgeschakeld zonder medaille → "Uitgeschakeld", géén plaats.
- Anders de **komende partij**: rondenaam (`1/4`, `1/2`, `Finale` — via `BracketLayoutService::
  rondeNaam()`) + tegenstander (naam + club). Tegenstander-slot nog leeg → "nog niet bekend".

`PubliekController@favorieten` verrijkt elke favoriet-judoka in een eliminatie-poule met een
`eliminatie`-object (`bouwEliminatieInfo()`); round-robin poules krijgen `eliminatie: null` en houden
de ranglijst-kaart. De statusbanners (blauw/geel/groen) en push-meldingen draaien op de per-judoka
flags en werken voor beide types ongewijzigd. De blade leest de info via de component-methode
`favorietEliminatie(poule)` (CSP-safe: geen optional chaining in Alpine-expressies).

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


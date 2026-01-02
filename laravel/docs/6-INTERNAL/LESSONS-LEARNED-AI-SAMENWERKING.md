# AI-Mens Samenwerking bij Complexe Logica

> Bron: JudoToernooi blokverdeling algoritme (dec 2025)

## Kernles

Bij logica-intensieve problemen: **stel eerst vragen, bouw daarna**.

## Rolverdeling

| Mens | AI |
|------|-----|
| Domeinkennis & regels | Implementatie-snelheid |
| Overzicht oplossingsrichtingen | Technische varianten aandragen |
| "Wat is goed?" definiëren | Snel itereren & testen |
| Fijntuning & validatie | Bulk-berekeningen |

## Aanpak bij complexe algoritmes

1. **Bespreek eerst de regels** - Wat maakt een oplossing "goed"?
2. **Definieer scoring** - Hoe meet je kwaliteit?
3. **Itereer samen** - AI bouwt, mens valideert
4. **Combineer** - Automatisch berekenen + handmatig fijntunen

## Anti-pattern

Niet doen: AI blind een solver laten bouwen zonder domeincontext.

## Patroon: Automatisch + Handmatig

```
[Algoritme berekent varianten]
        ↓
[Gebruiker kiest basis]
        ↓
[Handmatige aanpassingen]
        ↓
[Live feedback (score)]
        ↓
[Toepassen]
```

Dit patroon werkt voor elke verdeling/planning waar:
- Meerdere geldige oplossingen bestaan
- Domeinexpert fijntuning wil
- Directe feedback waardevol is

## Toepasbaar op

- Roosterplanning
- Resource allocatie
- Poule-indelingen
- Taak/team verdeling
- Route optimalisatie


Technische kant:

# Technisch Patroon: Verdeling met Live Feedback

> Bron: JudoToernooi blokverdeling (dec 2025)
> Stack: Laravel + Blade + Vanilla JS

## Architectuur

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND                              │
├─────────────────────────────────────────────────────────────┤
│  [Varianten knoppen]  ←── PHP session data                  │
│         ↓                                                    │
│  [Drag & drop chips]  ←── DOM state                         │
│         ↓                                                    │
│  [Live score display] ←── JS berekening                     │
│         ↓                                                    │
│  [Toepassen knop]     ──→ POST huidige DOM state            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                        BACKEND                               │
├─────────────────────────────────────────────────────────────┤
│  Controller::genereerVerdeling()                            │
│    - Clear oude session                                      │
│    - Service genereert N varianten                          │
│    - Sla varianten + stats op in session                    │
│    - Auto-apply beste variant                               │
│    - Redirect met ?kies=1                                   │
│                                                              │
│  Controller::kiesVariant()                                  │
│    - Accept toewijzingen array (uit DOM)                    │
│    - Of legacy: variant index uit session                   │
│    - Service past toe op database                           │
└─────────────────────────────────────────────────────────────┘
```

## Kerncomponenten

### 1. Varianten Generator (PHP Service)

```php
public function genereerVarianten($toernooi, $gewichtVerdeling, $gewichtAansluiting): array
{
    // Brute force met timeout
    $maxTijd = 3; // seconden
    $start = microtime(true);

    while (microtime(true) - $start < $maxTijd) {
        $variant = $this->maakRandomVerdeling();
        $score = $this->berekenScore($variant, $gewichtVerdeling, $gewichtAansluiting);

        if ($this->isGeldig($variant)) {
            $alleVarianten[] = ['toewijzingen' => $variant, 'scores' => $score];
        }
    }

    // Sorteer op score, neem top 5 unieke
    usort($alleVarianten, fn($a, $b) => $a['totaal_score'] <=> $b['totaal_score']);

    return [
        'varianten' => array_slice($this->uniqueVarianten($alleVarianten), 0, 5),
        'stats' => ['pogingen' => $poging, 'geldige' => count($alleVarianten), ...]
    ];
}
```

### 2. Live Score Berekening (JavaScript)

```javascript
let heeftWijzigingen = false;

function berekenLiveScore() {
    // 1. Lees huidige staat uit DOM
    const toewijzingen = {};
    document.querySelectorAll('.category-chip').forEach(chip => {
        const blokZone = chip.closest('.blok-dropzone');
        toewijzingen[chip.dataset.key] = parseInt(blokZone?.dataset?.blok || 0);
    });

    // 2. Bereken score componenten
    const verdelingScore = berekenVerdelingScore(toewijzingen);
    const aansluitingScore = berekenAansluitingScore(toewijzingen);
    const totaal = gewichtV * verdelingScore + gewichtA * aansluitingScore;

    // 3. Toon alleen na wijzigingen
    if (heeftWijzigingen) {
        const origineel = getOrigineelScore();
        const verschil = totaal - origineel;
        toonLiveScore(totaal, verschil);
    }
}
```

### 3. DOM → Backend Sync (Toepassen)

```javascript
function pasVariantToe() {
    // Lees HUIDIGE staat uit DOM (inclusief handmatige wijzigingen)
    const toewijzingen = {};
    document.querySelectorAll('.category-chip').forEach(chip => {
        const blokNr = parseInt(chip.closest('.blok-dropzone')?.dataset?.blok || 0);
        if (blokNr > 0) {
            toewijzingen[chip.dataset.key] = blokNr;
        }
    });

    fetch('/kies-variant', {
        method: 'POST',
        body: JSON.stringify({ toewijzingen })
    });
}
```

### 4. Session Management

```php
// Bij berekenen: clear eerst
session()->forget(['varianten', 'stats']);

// Na berekenen: sla op
session(['varianten' => $result['varianten']]);
session(['stats' => $result['stats']]);

// Bij toepassen: clear
session()->forget('varianten');
```

## UI Componenten

### Draggable Chips

```html
<div class="category-chip"
     draggable="true"
     data-key="Mini's|-20"
     data-leeftijd="Mini's"
     data-wedstrijden="12"
     data-vast="0">
    Mini's -20 (12w)
</div>
```

### Drop Zones

```html
<div class="blok-dropzone" data-blok="1">
    <!-- chips worden hier in gesleept -->
</div>
```

### Live Score Display

```html
<span id="live-score-display" class="hidden">
    | Huidige score: <span class="font-bold text-green-600">45</span>
    <span class="text-green-600">(↓-5.2)</span>
</span>
```

## Sleutelprincipes

1. **Session voor tijdelijke data** - Varianten leven alleen tijdens keuzeproces
2. **DOM als source of truth** - Bij toepassen wordt DOM uitgelezen, niet session
3. **Lazy feedback** - Live score pas tonen na eerste wijziging
4. **Originele scores behouden** - Knoppen tonen PHP scores, niet JS herberekening
5. **Graceful degradation** - Werkt ook zonder JS (form submit)

## Herbruikbare Patronen

| Patroon | Toepassing |
|---------|------------|
| Brute force + timeout | Wanneer optimale oplossing te duur is |
| Session varianten | Meerdere opties presenteren voor keuze |
| DOM → Backend sync | Handmatige aanpassingen meenemen |
| Live score feedback | Directe validatie van wijzigingen |
| Verschil-indicator | Gebruiker ziet impact van actie |

## Valkuilen Vermeden

- ❌ Variant index sturen i.p.v. huidige staat → handmatige wijzigingen kwijt
- ❌ JS scores op knoppen tonen → inconsistente sortering
- ❌ Live score altijd tonen → verwarrend bij verse berekening
- ❌ Chips in sleepvak meetellen → NaN in berekening

---

## Les: Visuele Componenten Nabouwen

> Bron: Lege wedstrijdschema's print versie (dec 2025)

### Probleem

Bij het bouwen van een print-versie van het wedstrijdschema ging veel tijd verloren door:
- Zelf oplossingen verzinnen i.p.v. het voorbeeld volgen
- Niet goed kijken naar het getoonde voorbeeld
- Domeinkennis veronderstellen (judopunten verkeerd)

### Aanpak bij visuele componenten

1. **Analyseer het voorbeeld eerst grondig**
   - Welke kolommen/rijen?
   - Welke kleuren/borders?
   - Wat zijn de verhoudingen?

2. **Vraag bij twijfel, verzin niets**
   - Judopunten: Yuko=5, Waza-Ari=7, Ippon=10 (niet zelf invullen!)
   - Wedstrijdpunten: 0 of 2 (niet 0-10)

3. **Print ≠ Screen**
   - Browser print CSS override achtergrondkleuren
   - `print-color-adjust: exact` nodig voor kleuren
   - `@stack('styles')` volgorde belangrijk voor `@page` overrides

4. **Test beide: screen preview EN daadwerkelijke print**
   - Screen preview kan correct zijn terwijl print fout is
   - Landscape/portrait werkt anders in print

### Anti-pattern

❌ "Ik maak wel iets dat lijkt op..." → Kost veel iteraties
✅ "Ik bouw exact na wat ik zie" → Direct goed

---

## Les: Context-Afhankelijke Tellingen (Voorbereiding vs Wedstrijddag)

> Bron: Judoka/wedstrijden count mismatch (dec 2025, herzien dec 2025)

### Probleem

Tellingen van judoka's en wedstrijden moeten ANDERS behandeld worden afhankelijk van de context:

| Context | Wat tellen? | Bron |
|---------|-------------|------|
| **Voorbereiding** | Alle ingeschreven judoka's | Database (`$poule->aantal_judokas`) |
| **Wedstrijddag** | Alleen ACTIEVE judoka's | Herberekenen op basis van afwezigheid |

### KRITISCH: Wedstrijddag vs Voorbereiding

**Op de wedstrijddag:**
- Judoka's kunnen afwezig zijn (niet komen opdagen)
- Judoka's kunnen afwijkend gewogen zijn (ompolen naar andere klasse)
- Het aantal wedstrijden hangt af van het WERKELIJKE aantal deelnemers

**Voorbeeld:**
- Voorbereiding: 29 judoka's ingeschreven → 53 wedstrijden (dubbel KO)
- Wedstrijddag: 2 afwezig → 27 actieve judoka's → 49 wedstrijden

### Voorbereiding: Database als bron

```php
// GOED voor poule-indeling, blokken plannen
$aantal = $poule->aantal_judokas;
$wedstrijden = $poule->aantal_wedstrijden;
```

### Wedstrijddag: ALTIJD herberekenen

```php
// VERPLICHT voor wedstrijddag interface
$actieveJudokas = $poule->judokas->filter(
    fn($j) => !$j->moetUitPouleVerwijderd($tolerantie)
)->count();

// Wedstrijden herberekenen op basis van ACTIEVE judoka's!
$aantalWedstrijden = $poule->berekenAantalWedstrijden($actieveJudokas);
```

### Implementatie in code

```php
// wedstrijddag/poules.blade.php - ALTIJD herberekenen
$actief = $poule->judokas->filter(fn($j) => !$j->moetUitPouleVerwijderd($tolerantie))->count();
$wedstrijden = $poule->berekenAantalWedstrijden($actief);

// WedstrijddagController - bij drag & drop
$actieveJudokasNieuw = $nieuwePoule->judokas->filter(...)->count();
return response()->json([
    'aantal_judokas' => $actieveJudokasNieuw,
    'aantal_wedstrijden' => $nieuwePoule->berekenAantalWedstrijden($actieveJudokasNieuw),
]);
```

### Regels

**Voorbereiding (poule-indeling, blokken):**
1. ✅ Gebruik `$poule->aantal_judokas` en `$poule->aantal_wedstrijden`
2. ✅ Update database na mutaties met `updateStatistieken()`

**Wedstrijddag:**
1. ✅ ALTIJD actieve judoka's tellen (excl. afwezig/afwijkend)
2. ✅ ALTIJD wedstrijden herberekenen: `berekenAantalWedstrijden($actief)`
3. ❌ NOOIT database waarde `$poule->aantal_wedstrijden` gebruiken

### Anti-pattern (FOUT!)

```php
// FOUT op wedstrijddag - dit is de database waarde, niet de werkelijke situatie
$aantalWedstrijden = $poule->aantal_wedstrijden;
```

### Waarom dit steeds fout ging

Dit patroon werd 10+ keer "gefixt" omdat:
1. De fix voor voorbereiding (database als bron) werd verkeerd toegepast op wedstrijddag
2. De context (voorbereiding vs wedstrijddag) werd niet meegenomen
3. Er stond incorrecte documentatie die zei "database is altijd de bron"

**ONTHOUD: Wedstrijddag = dynamische data, voorbereiding = statische data**

---

## Les: Sortable.js onEnd Handler Locatie

> Bron: Drag & drop wachtruimte naar poule werkte niet (dec 2025)

### Probleem

Bij drag & drop van wachtruimte naar poule werden de poule stats niet bijgewerkt. Debug logging toonde dat de code niet werd uitgevoerd.

### Oorzaak

**Sortable.js roept de `onEnd` handler aan van de SOURCE container, niet de TARGET!**

```
Drag van: wachtruimte (container A)
Drag naar: poule (container B)

→ A's onEnd wordt aangeroepen, NIET B's onEnd
```

### Foute aanname

```javascript
// Poule container - verwachtte dat dit werd aangeroepen
new Sortable(pouleContainer, {
    onEnd: function(evt) {
        // Deze code draait NIET bij drag VAN wachtruimte
    }
});
```

### Correcte oplossing

```javascript
// Wachtruimte container - HIER zit de logica voor drag naar poule
new Sortable(wachtruimteContainer, {
    group: { name: 'shared', pull: true, put: false },
    onEnd: function(evt) {
        const naarPouleId = evt.to.dataset.pouleId;
        updatePouleFromDOM(naarPouleId);  // Update TARGET poule
    }
});
```

### Regel

Bij Sortable.js met meerdere containers:
- `onEnd` wordt aangeroepen op de **bron** container
- Gebruik `evt.from` voor source, `evt.to` voor target
- Zet de update logica in de container waar je **vandaan** sleept


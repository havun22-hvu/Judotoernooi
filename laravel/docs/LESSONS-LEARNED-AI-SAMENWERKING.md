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




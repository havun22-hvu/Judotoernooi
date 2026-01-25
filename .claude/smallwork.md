# Smallwork Log

> Kleine technische fixes die niet in permanente docs hoeven.
>
> **Wat hoort hier:**
> - Bug fixes, typos, performance
> - Technische refactoring
>
> **Wat hoort hier NIET:**
> - Features → docs/
> - Styling → STYLING.md
>
> **Archief:** Oude sessies staan in `archive/`

---

## Sessie: 25 januari 2026

### Fix: Variabele gewichtscategorieën
- **Type:** Bug fix
- **Wat:** Per-poule `isDynamisch()` check i.p.v. globaal
- **Bestanden:** Wedstrijddag controller + views

### Fix: Poule breedte
- **Type:** UI fix
- **Wat:** Grid layout (grid-cols-3) i.p.v. flex-wrap met min-width
- **Bestanden:** Wedstrijddag poules view

### Fix: VARIABEL vs VAST toernooi layout
- **Type:** Bug fix
- **Wat:** Aparte layouts voor variabel (4 kolommen, geen headers) vs vast (headers + wachtruimte)
- **Bestanden:** poules.blade.php, ToernooiController.php

### Fix: Titel formaat met slashes
- **Type:** UI improvement
- **Wat:** `#1 Jeugd / 5-7j / 16.1-18.3kg` i.p.v. `#1 Jeugd 5-7j 16.1-18.3kg`
- **Bestanden:** poule-card.blade.php, poules.blade.php, poule/index.blade.php

### Fix: Eliminatie poule UX
- **Type:** UI improvement
- **Wat:** Zoekfunctie per judoka, info tooltip, → naar matten knop
- **Bestanden:** poules.blade.php

### Feat: Nieuwe poule knop in blokbalk
- **Type:** Feature
- **Wat:** Groene "+ Poule" knop in wedstrijddag blokbalk
- **Bestanden:** poules.blade.php, WedstrijddagController.php

---

<!--
TEMPLATE:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor
- **Wat:** [wat aangepast]
- **Bestanden:** [welke files]
-->

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

### Fix: Weegkaart modal skip voor portal
- **Type:** Bug fix
- **Wat:** "Weegkaart opslaan?" modal niet tonen bij portal/organisator toegang, alleen bij QR-scan smartphone
- **Bestanden:** weegkaart/show.blade.php, coach/weegkaarten.blade.php, judoka/show.blade.php
- **Oplossing:** `?from_portal` query parameter

### Fix: Weegkaart band zonder kyu
- **Type:** UI fix
- **Wat:** Band tonen als "Blauw" i.p.v. "Blauw (2e kyu)"
- **Bestanden:** weegkaart/show.blade.php
- **Regel:** `explode(' ', $judoka->band)[0]` - alleen eerste woord

### Fix: Nieuwe lege poules niet zichtbaar
- **Type:** Bug fix
- **Wat:** Handmatig aangemaakte lege poules werden uitgefilterd bij variabel gewicht
- **Bestanden:** WedstrijddagController.php
- **Oplossing:** Poules aangemaakt binnen 24h altijd tonen

### Fix: Eliminatie poule te weinig judoka's
- **Type:** Bug fix
- **Wat:** Eliminatie poules met <8 judoka's nu ook als problematisch (rood) gemarkeerd
- **Bestanden:** poule-card.blade.php
- **Regel:** `$isProblematisch = $aantalActief > 0 && $aantalActief < ($isEliminatie ? 8 : 3)`

### Feat: Barrage systeem voor 3-weg gelijkspel
- **Type:** Feature
- **Wat:** Detecteert 3+ judoka's met gelijke WP+JP en cirkel-verliezen, toont "Barrage" knop
- **Bestanden:** BlokController.php, mat/_content.blade.php, Poule.php
- **Migration:** barrage_van_poule_id in poules tabel
- **Logica:** Barrage poule wordt aangemaakt op zelfde mat, judoka's blijven ook in originele poule

### Fix: Poule verplaatsen vereenvoudigd
- **Type:** Bug fix
- **Wat:** verplaatsPoule update alleen mat_id, geen onnodige resets meer
- **Bestanden:** BlokController.php
- **Behouden:** Alle wedstrijden, scores, voortgang intact bij verplaatsen

### Feat: Mat interface auto-refresh
- **Type:** Feature
- **Wat:** Mat interface refresht elke 30 sec voor verplaatste poules
- **Bestanden:** mat/_content.blade.php
- **Logica:** `setInterval(() => laadWedstrijden(), 30000)`

### Fix: Barrage altijd single round-robin
- **Type:** Bug fix
- **Wat:** Barrage poules negeren dubbel_bij_3_judokas config, altijd 1x tegen elkaar
- **Bestanden:** WedstrijdSchemaService.php
- **Logica:** Check `$poule->type === 'barrage'` → force single round-robin schema

---

## Sessie: 26 januari 2026

### Fix: Spreker oproepen filter
- **Type:** Bug fix
- **Wat:** Alleen doorgestuurde poules tonen in oproepen tab
- **Bestanden:** BlokController.php, RoleToegang.php
- **Filter:** `whereNotNull('doorgestuurd_op')`

### Fix: Spreker auto-refresh vervangen
- **Type:** UI improvement
- **Wat:** Vervelende 10s auto-refresh vervangen door handmatige "Vernieuwen" knop
- **Bestanden:** spreker/_content.blade.php

### Feat: Spreker geschiedenis klikbaar
- **Type:** Feature
- **Wat:** Eerder afgeroepen poules klikbaar → modal met uitslagen
- **Bestanden:** spreker/_content.blade.php, BlokController.php, RoleToegang.php, web.php
- **Route:** `POST /spreker/standings`

### Fix: Organisator kan toernooi niet verwijderen
- **Type:** Bug fix
- **Wat:** Na delete werd geredirect naar /toernooi (sitebeheerder-only), nu naar dashboard
- **Bestanden:** ToernooiController.php
- **Oplossing:** Sitebeheerder → toernooi.index, organisator → organisator.dashboard

### Feat: Standaard categorie bij nieuw toernooi
- **Type:** Feature
- **Wat:** Bij nieuw toernooi (zonder template) wordt standaard categorie aangemaakt
- **Bestanden:** ToernooiService.php
- **Config:** max_lft=99, v.lft=1, v.kg=3, v.band=2, band_streng_beginners=true

---

<!--
TEMPLATE:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor
- **Wat:** [wat aangepast]
- **Bestanden:** [welke files]
-->

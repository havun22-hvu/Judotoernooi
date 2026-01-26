# Handover

## Laatste Sessie: 26 januari 2026

### Wat is gedaan:
- **Barrage single round-robin**: Barrage poules negeren dubbel_bij_3 config, altijd 1x tegen elkaar
- **Spreker oproepen filter**: Alleen doorgestuurde poules tonen in oproepen tab
- **Spreker sync knop**: Auto-refresh (10s) vervangen door handmatige "Vernieuwen" knop
- **Spreker geschiedenis klikbaar**: Eerder afgeroepen poules klikbaar → modal met uitslagen

### Belangrijke context:
- `WedstrijdSchemaService::getOptimaleWedstrijdvolgorde()` checkt `$poule->type === 'barrage'`
- Spreker interface heeft nu `toonPouleDetail(pouleId)` functie + modal
- Nieuwe route: `POST /spreker/standings` voor ophalen poule uitslagen
- `berekenPouleStand()` methode toegevoegd aan BlokController + RoleToegang

---

## Vorige Sessie: 25 januari 2026 (nacht)

### Wat is gedaan:
- **Barrage systeem** voor 3-weg gelijkspel:
  - Detecteert 3+ judoka's met gelijke WP+JP die cirkel-verliezen vormen
  - "Barrage" knop in mat interface titelbalk
  - Barrage poule op zelfde mat, judoka's blijven ook in originele poule
  - Nieuw poule type: `barrage`, nieuw veld: `barrage_van_poule_id`
- **Eliminatie <8 judoka's**: Nu ook rood (problematisch) gemarkeerd
- **Poule verplaatsen**: Vereenvoudigd - alleen mat_id wijzigt, scores intact
- **Mat interface auto-refresh**: Elke 30 sec voor verplaatste poules
- **Docs opgeruimd**: .gitignore, .claude/archive/, deploy.md, features.md, mollie.md

### Belangrijke context:
- Barrage detectie logica in `mat/_content.blade.php`:
  - `heeftBarrageNodig(poule)` - check afgerond + gelijke stand + cirkel
  - `isCircleResult(poule, judokas)` - niemand wint van iedereen
- Backend: `BlokController::maakBarrage()` maakt barrage poule aan
- Judoka's worden `attach()` (niet `detach`) - blijven in beide poules
- Poule verplaatsen: zaaloverzicht drag & drop → mat_id update → auto-refresh
- **Barrage wedstrijdschema**: Altijd single round-robin (1x tegen elkaar), negeert dubbel_bij_3_judokas config
- Bij barrage gelijkspel: nieuwe barrage poule wordt aangemaakt

### Openstaande items:
- Geen

### Technische notities:
- BandHelper::BAND_VOLGORDE is omgekeerd: wit=6, zwart=0
- SQLite: bij tabel hernoemen worden FK's in andere tabellen mee hernoemd
- `$isProblematisch = $aantalActief > 0 && $aantalActief < ($isEliminatie ? 8 : 3)`
- Mat interface refresh: `setInterval(() => laadWedstrijden(), 30000)`

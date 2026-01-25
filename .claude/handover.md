# Handover

## Laatste Sessie: 25 januari 2026 (nacht)

### Wat is gedaan:
- **Barrage systeem** voor 3-weg gelijkspel:
  - Detecteert 3+ judoka's met gelijke WP+JP die cirkel-verliezen vormen
  - "Barrage" knop in mat interface titelbalk
  - Barrage poule op zelfde mat, judoka's blijven ook in originele poule
  - Nieuw poule type: `barrage`, nieuw veld: `barrage_van_poule_id`
- **Eliminatie <8 judoka's**: Nu ook rood (problematisch) gemarkeerd
- **Docs opgeruimd**: .gitignore, .claude/archive/, deploy.md, features.md, mollie.md

### Belangrijke context:
- Barrage detectie logica in `mat/_content.blade.php`:
  - `heeftBarrageNodig(poule)` - check afgerond + gelijke stand + cirkel
  - `isCircleResult(poule, judokas)` - niemand wint van iedereen
- Backend: `BlokController::maakBarrage()` maakt barrage poule aan
- Judoka's worden `attach()` (niet `detach`) - blijven in beide poules

### Openstaande items:
- Geen

### Technische notities:
- BandHelper::BAND_VOLGORDE is omgekeerd: wit=6, zwart=0
- SQLite: bij tabel hernoemen worden FK's in andere tabellen mee hernoemd
- `$isProblematisch = $aantalActief > 0 && $aantalActief < ($isEliminatie ? 8 : 3)`

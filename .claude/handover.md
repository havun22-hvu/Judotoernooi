# Handover

## Laatste Sessie: 25 januari 2026 (avond)

### Wat is gedaan:
- **VARIABEL vs VAST toernooi layout**: Aparte @if branches voor verschillende layouts
  - VARIABEL: 4 kolommen, geen headers, geen wachtruimte, eliminatie volle breedte
  - VAST: headers per categorie + wachtruimte in 4e kolom
- **Titel formaat met slashes**: `#1 Jeugd / 5-7j / 16.1-18.3kg`
- **Eliminatie poule UX verbeteringen**:
  - Zoekfunctie (🔍) per judoka bij hover
  - Info tooltip (ⓘ) voor afwezige/overgepoulde judoka's
  - → naar matten knop toegevoegd
  - Groene stip (●) voor gewogen judoka's
- **Titelbalk selectable**: `pointer-events-none` verwijderd
- **Controller fix**: `gebruik_gewichtsklassen` wordt niet meer overschreven bij edit

### Belangrijke context:
- `$heeftVariabeleCategorieen = !$toernooi->gebruik_gewichtsklassen`
- Test-2026 toernooi: `gebruik_gewichtsklassen = false` (variabel)
- Titel regex: `/^(.+?)\s+(\d+-?\d*j)\s+(.+)$/` voor parsing

### Openstaande items:
- Geen

### Technische notities:
- BandHelper::BAND_VOLGORDE is omgekeerd: wit=6, zwart=0
- SQLite: bij tabel hernoemen worden FK's in andere tabellen mee hernoemd

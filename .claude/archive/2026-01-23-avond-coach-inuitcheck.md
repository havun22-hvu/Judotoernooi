# Session Handover 23 januari avond - Coach In/Uitcheck Systeem

## Gebouwd deze sessie

Compleet coach check-in/check-out systeem voor dojo toegang:

### 1. Dojo Scanner Overzicht (nieuw)
- **Locatie:** `pages/dojo/scanner.blade.php`
- 2 tabs: Scanner + Overzicht
- Club zoeken met tekst filter
- Per club: ingecheckt/uitgecheckt/ongebruikt tellers
- Na QR scan → automatisch juiste club selecteren

### 2. Portal Coachkaarten (uitgebreid)
- **Locatie:** `pages/coach/coachkaarten.blade.php`
- Rich cards met foto's van coaches
- "Geschiedenis" knop → alle vorige coaches met foto's en in/uit tijden
- Check-in/out status zichtbaar als systeem actief

### 3. Organisator Portal (uitgebreid)
- **Locatie:** `pages/coach-kaart/index.blade.php`
- Toggle knop voor coach_incheck_actief
- Statistiek: ingecheckt aantal (klikbaar)
- Link naar beheer pagina

### 4. Force Checkout (nieuw)
- **Locatie:** `pages/coach-kaart/ingecheckt.blade.php`
- Lijst alle ingecheckte coaches met foto
- Force checkout knop per coach
- Alleen voor hoofdjury (organisator portal)
- Gelogd als "uit_geforceerd"

## Database wijzigingen
- `coach_checkins` tabel (alle in/uit acties met foto snapshot)
- `coach_kaarten.ingecheckt_op` timestamp
- `toernooien.coach_incheck_actief` boolean

## Te testen morgen (met meerdere mensen)

### Test scenario's:
1. **Basis flow:**
   - Coach activeert kaart (foto + naam + PIN)
   - Scan bij dojo → check-in
   - Scan bij vertrek → check-out

2. **Overdracht blokkade:**
   - Coach A is ingecheckt
   - Coach B probeert kaart over te nemen
   - Moet HARD geblokkeerd worden (geen "toch overdragen")

3. **Force checkout:**
   - Coach vergeet uit te checken
   - Hoofdjury kan forceren in organisator portal
   - Daarna kan kaart overgedragen worden

4. **Overzicht tabs:**
   - Dojo scanner: overzicht per club
   - Portal: geschiedenis per kaart
   - Organisator: totaal overzicht

### Routes om te testen:
- `/dojo/{toernooi}` - Dojo scanner met tabs
- `/coach/{code}` → Coach kaarten tab - Portal met foto's
- `/toernooi/{id}/coach-kaarten` - Organisator overzicht
- `/toernooi/{id}/coach-kaarten/ingecheckt` - Force checkout

## Bekende aandachtspunten
- Check-in systeem moet expliciet geactiveerd worden per toernooi
- Overdracht is HARD blocked als coach ingecheckt (niet optioneel)
- Alle acties worden gelogd in coach_checkins tabel

## Commit
`8745b74` - feat: Add coach check-in history and force checkout for hoofdjury

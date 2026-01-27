# Handover

## Laatste Sessie: 27 januari 2026 (avond)

### Wat is gedaan:
- **URL fixes na restructuring**:
  - Dashboard template delete: `/templates/${id}` → `/${org}/templates/${id}`
  - Dojo API routes gemigreerd: `/dojo/{toernooi}/...` → `/{org}/{toernooi}/dojo/...`
  - Scanner.blade.php JavaScript aangepast voor nieuwe URLs
  - URL-STRUCTUUR.md bijgewerkt met sectie 9 (Dojo Scanner API)

### Getest (27 jan avond):
- [x] Dashboard: template verwijderen werkt (route correct)
- [x] Dojo scanner: overzicht tab laadt clubs (200 OK, JSON response)
- [x] Dojo scanner: club detail laadt kaarten (200 OK, JSON response)

### Openstaande items:

#### Freemium Model - VOLLEDIG GEÏMPLEMENTEERD ✓
- [x] Database migrations (toernooi_betalingen, freemium velden)
- [x] ToernooiBetaling model
- [x] FreemiumService
- [x] Limiet checks (judoka's in JudokaController + CoachPortalController)
- [x] Upgrade flow met Mollie Platform payments
- [x] Routes, controller, views

**Docs:** `laravel/docs/2-FEATURES/FREEMIUM.md`

#### Nog te testen:
- [ ] Freemium upgrade flow end-to-end
- [ ] Coach portal werking
- [ ] Device toegang routes

### Belangrijke context:
- `Toernooi::routeParams()` returnt `['organisator' => $this->organisator, 'toernooi' => $this]`
- `Toernooi::routeParamsWith(['key' => 'value'])` voor extra parameters
- Publieke routes: `/{organisator}/{toernooi}/...` (zonder `/toernooi/` in pad)
- Authenticated routes: `/{organisator}/toernooi/{toernooi}/...`

### Technische notities:
- CRLF warnings bij git zijn normaal op Windows, geen actie nodig
- GitHub repo URL gewijzigd naar `https://github.com/havun22-hvu/Judotoernooi.git`

---

## Vorige Sessie: 26 januari 2026

### Wat is gedaan:
- **Barrage single round-robin**: Barrage poules negeren dubbel_bij_3 config, altijd 1x tegen elkaar
- **Spreker oproepen filter**: Alleen doorgestuurde poules tonen in oproepen tab
- **Spreker sync knop**: Auto-refresh (10s) vervangen door handmatige "Vernieuwen" knop
- **Spreker geschiedenis klikbaar**: Eerder afgeroepen poules klikbaar → modal met uitslagen

# Handover

## Laatste Sessie: 27 januari 2026 (avond)

### Wat is gedaan:
- **URL fixes na restructuring**:
  - Dashboard template delete: `/templates/${id}` → `/${org}/templates/${id}`
  - Dojo API routes gemigreerd: `/dojo/{toernooi}/...` → `/{org}/{toernooi}/dojo/...`
  - Scanner.blade.php JavaScript aangepast voor nieuwe URLs
  - URL-STRUCTUUR.md bijgewerkt met sectie 9 (Dojo Scanner API)

### Nog te testen:
- [ ] Dashboard: template verwijderen werkt
- [ ] Dojo scanner: overzicht tab laadt clubs
- [ ] Dojo scanner: club detail laadt kaarten

### Openstaande items:

#### Freemium Model Implementatie
Plan staat in `.claude/plans/majestic-chasing-token.md`:
- [ ] Database migrations (toernooi_betalingen, freemium velden)
- [ ] ToernooiBetaling model
- [ ] FreemiumService
- [ ] Limiet checks (50 judoka's, 2 clubs, 1 preset, 2 schemas)
- [ ] Print/noodplan geblokkeerd voor free tier
- [ ] Upgrade flow met Mollie Platform payments

**Gratis tier limieten:**
| Limiet | Waarde |
|--------|--------|
| Max judoka's | 50 |
| Max actieve clubs | 2 |
| Max presets | 1 |
| Max wedstrijdschema's | 2 |
| Print/noodplan | Uitgeschakeld |

#### Nog te testen:
- [ ] Alle pagina's doorlopen met nieuwe URL structuur
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

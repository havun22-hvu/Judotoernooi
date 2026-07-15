---
title: Display-view (web, in JudoToernooi)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Display-view (web, in JudoToernooi)

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

## Display View (Web — in JudoToernooi)

### Route
`/{slug}/{toernooi}/mat/scoreboard-live/{matId}`

### Hoe het werkt
1. Bediening (Android app) POST events naar `/api/scoreboard/event` (alleen bij state changes)
2. Server broadcast via Reverb op channel `scoreboard-display.{toernooiId}.{matId}`
3. Blade view luistert via Reverb → draait eigen timer lokaal, update scores bij events
4. Geen knoppen, alleen weergave
5. Gespiegeld t.o.v. bediening (Wit links, Blauw rechts)

### Voordelen web display
- TV/LCD hoeft alleen browser te openen (geen APK installeren)
- Werkt op elk device met browser
- Kan ook via Chromecast/HDMI laptop → TV

### TV Koppelen

TV opent `/tv` → toont 4-cijferige code én QR-code (beide wijzen naar dezelfde koppeling).
Code is 10 minuten geldig. Elke koppeling gebruikt channel `tv-koppeling.{code}` voor de redirect.

**Drie manieren om te koppelen:**

| Methode | Wie | Flow |
|---------|-----|------|
| **Code handmatig** | Organisator (laptop) | Instellingen → Device Toegangen → "Koppel TV" → code invoeren → `POST /tv/link` |
| **QR met telefoon** | Organisator | Scan QR met camera → `GET /tv/qr/{code}` (auth) → kies toernooi + mat → `POST /tv/link` |
| **QR met scorebord-app** | Tafelofficial | Scan QR in-app → `POST /api/scoreboard/tv-link` → mat uit Bearer token |

**Backend resultaat (alle 3 paden identiek):**
1. `tv_koppelingen` rij krijgt `toernooi_id` + `mat_nummer` + `linked_at`
2. `TvLinked` event dispatched op channel `tv-koppeling.{code}` met `redirect` URL
3. TV (die luistert via Reverb op die channel) navigeert naar scoreboard-live pagina

**Redirect URL logica** (in alle 3 endpoints):
- Bestaat er een `mat`-rol DeviceToegang voor die mat? → `/tv/{eerste 4 chars van code}` (kort, cacheable)
- Anders → volledige `mat.scoreboard-live` route (fallback)

#### Route `GET /tv` (TV koppel pagina)
Publieke Blade view. Genereert unieke 4-cijferige code + QR (SVG via simple-qrcode).
QR bevat URL `{APP_URL}/tv/qr/{code}`. Rendert ook een Reverb-client die op `tv.linked`
events luistert voor automatische redirect.

#### Route `GET /tv/qr/{code}` (webapp QR-landing)
**Auth vereist** (organisator login). Regex `[0-9]{4}`. Blade view met drie statussen:
- `expired` — code niet gevonden of verlopen
- `already-linked` — code al gekoppeld
- `ready` — toont toernooi-dropdown (actieve toernooien van de organisator; sitebeheerder ziet alle actieve) en mat-knoppen per toernooi

Na mat-keuze: POST naar bestaande `/tv/link` endpoint.

#### Route `POST /tv/link` (webapp koppel-call)
**Auth vereist.** Body: `{ code, toernooi_id, mat_nummer }`. Authorization: user moet
sitebeheerder zijn of eigenaar van het toernooi (`organisator_id` match).

#### Route `POST /api/scoreboard/tv-link` (app koppel-call)

**Auth:** Bearer token (`scoreboard` of `mat` rol, zelfde middleware als andere scoreboard API calls).

**Request:**
```json
{ "code": "1234" }
```

**Response (success, 200):**
```json
{ "success": true, "mat_nummer": 2 }
```

**Responses (error):**
| Status | Body | Wanneer |
|--------|------|---------|
| 401 | `{ "message": "Token ontbreekt." }` of `Ongeldig token.` | Geen/ongeldig Bearer token |
| 422 | `{ "success": false, "message": "Device is niet gekoppeld aan een mat." }` | Token heeft geen `toernooi_id`/`mat_nummer` |
| 422 | `{ "success": false, "message": "Code ongeldig of verlopen." }` | Code niet gevonden, verlopen of al gebruikt |

**Security:** `toernooi_id` en `mat_nummer` komen ALLEEN uit de Bearer token's DeviceToegang,
nooit uit het request body — voorkomt dat een gecompromitteerde app een andere mat kaapt.

---


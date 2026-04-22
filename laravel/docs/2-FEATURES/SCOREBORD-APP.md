---
title: Scorebord App — Android Bediening + Web Display
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# Scorebord App — Android Bediening + Web Display

> **Status:** In ontwikkeling (maart 2026)
> **Bediening:** Expo React Native (TypeScript) — apart project `D:\GitHub\JudoScoreBoard\`
> **Display:** Blade + Alpine.js + Reverb — in dit project (JudoToernooi)
> **Doel:** Judo scorebord op scheidsrechter-tablet/smartphone, gekoppeld aan wedstrijdsysteem

## Overzicht

Standalone Android app voor het bedienen van een judo scorebord (timer, scores, shido's, osaekomi).
Verbonden met JudoToernooi via API — ontvangt wedstrijden en stuurt uitslagen automatisch terug.

### Twee interfaces

| Interface | Device | Type | Functie |
|-----------|--------|------|---------|
| **Bediening** | Tablet, smartphone | Android APK | Alle knoppen: timer, score, shido, osaekomi |
| **Display** | TV, LCD, projector | Web (Blade + Reverb) | Alleen weergave, geen knoppen, **gespiegeld** |

### Spiegeling (IJF standaard)

De bediening en display zijn **gespiegeld** — de tafelofficial kijkt naar de mat,
de scheidsrechter kijkt naar de tafel. Wat links is voor de een, is rechts voor de ander.

| Interface | Links | Rechts |
|-----------|-------|--------|
| **Bediening** (tafelofficial) | Blauw | Wit |
| **Display** (scheidsrechter/publiek) | Wit | Blauw |

### Bediening: responsive (tablet + smartphone)

| Device | Layout |
|--------|--------|
| **Tablet** (10"+) | Volledig, ruim, alle info zichtbaar |
| **Smartphone** (5-7") | Compact, alles op 1 scherm, geen scrollen |

Beide landscape, zelfde functionaliteit, andere layout.

### Flow MET scorebord

```
Mat interface → "Groen" (actieve wedstrijd) → Scorebord app ontvangt wedstrijd
→ Scheidsrechter speelt wedstrijd op scorebord
→ Scorebord stuurt uitslag terug → Mat interface verwerkt automatisch
→ Groen vervalt, winnaar ingevuld
```

### Flow ZONDER scorebord (backward compatible)

```
Mat interface → Handmatig winnaar selecteren (wit/blauw)
→ Alles werkt zoals nu, niks verandert
```

---

## Scoring Regels

### Handmatig scoren (Y / W / I)

| Score | Teller | Beschrijving |
|-------|--------|--------------|
| **Yuko (Y)** | 0, 1, 2, ... | Aparte teller, optellend |
| **Waza-ari (W)** | 0, 1, 2 | 2 waza-ari = ippon (awasete ippon) |
| **Ippon (I)** | ja/nee | Direct ippon = wedstrijd voorbij |

### Osaekomi scoring

| Tijd | Score |
|------|-------|
| 5-9 sec | Yuko |
| 10-19 sec | Waza-ari |
| 20 sec | Ippon |

### Shido

- 3 gele kaartjes per judoka (vullen op bij elke shido)
- 3e shido = hansoku-make = ippon voor tegenstander

### Golden Score

- Bij gelijkspel na reguliere tijd
- Timer telt omhoog (geen limiet)
- Eerste score wint

### Geluiden

| Event | Geluid |
|-------|--------|
| Match einde | Triple beep (880Hz) |
| Osaekomi milestone (5s, 10s) | Single low beep (440Hz) |
| Ippon | Victory fanfare (C-E-G-C) |
| 30 seconden warning | Timer kleurt rood |

---

## Technische Architectuur

### Communicatie

| Richting | Methode | Channel/Endpoint |
|----------|---------|-----------------|
| Mat → Scorebord | Reverb WebSocket | `scoreboard.{toernooiId}.{matId}` |
| Scorebord → Mat | REST API POST | `POST /api/scoreboard/result` |
| Bediening → Display | Event-based sync via Reverb | `scoreboard-display.{toernooiId}.{matId}` |
**GEEN POLLING.** Altijd WebSocket (Reverb) voor real-time data.
`GET /current-match` is alleen voor initieel ophalen bij (re)connect.

### Sync strategie: event-based (niet continu)

De bediening stuurt **alleen events bij state changes**, niet continu state.
Het display draait zijn eigen timer lokaal. ~20-30 requests per wedstrijd totaal.

| Event | Wanneer | Frequentie |
|-------|---------|------------|
| `match.start` | Nieuwe wedstrijd geladen | 1x |
| `timer.start` | Timer start, met starttijd | 1x |
| `timer.stop` | Timer stopt, met resterende tijd | 1x |
| `timer.reset` | Timer reset | 1x |
| `score.update` | Score wijzigt (Y/W/I/shido) | Bij actie |
| `osaekomi.start` | Osaekomi start, met starttijd | 1x |
| `osaekomi.stop` | Osaekomi stopt | 1x |
| `match.end` | Winnaar bepaald | 1x |

**Vertraging:** ~200ms bij score wijziging — onmerkbaar voor publiek.

### Authenticatie

- Hergebruikt `DeviceToegang` systeem met nieuwe rol `scoreboard`
- App logt in met **code + pincode** → ontvangt Bearer **api_token**
- Token opgeslagen in AsyncStorage, meegestuurd als `Authorization: Bearer {token}`
- Geen CSRF nodig (API routes buiten web middleware)

### API Endpoints

```
POST /api/scoreboard/auth            → Login: code + pincode → token + config
GET  /api/scoreboard/current-match   → Huidige wedstrijd ophalen (alleen bij (re)connect)
POST /api/scoreboard/result          → Uitslag terugsturen
POST /api/scoreboard/event           → Sync event naar display (event-based, niet continu)
POST /api/scoreboard/heartbeat       → Verbinding alive houden
POST /api/scoreboard/tv-link         → TV koppelen via QR (body: { code }) — mat uit token
```

#### POST /api/scoreboard/auth
**Request:**
```json
{
  "code": "ABC123DEF456",
  "pincode": "1234"
}
```
**Response:**
```json
{
  "token": "64-char-api-token",
  "toernooi_id": 1,
  "mat_id": 3,
  "mat_naam": "Mat 3",
  "reverb_config": {
    "host": "judotournament.org",
    "port": 443,
    "scheme": "https",
    "app_key": "..."
  }
}
```

#### GET /api/scoreboard/current-match
**Response:**
```json
{
  "match": {
    "id": 101,
    "judoka_wit": { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord" },
    "judoka_blauw": { "id": 43, "naam": "Piet Jansen", "club": "Sportclub Oost" },
    "poule_naam": "B-pupillen -34 kg Poule 5",
    "ronde": null,
    "match_duration": 240
  },
  "updated_at": "2026-03-21T14:30:00Z"
}
```

#### POST /api/scoreboard/result
**Request:**
```json
{
  "wedstrijd_id": 101,
  "winnaar_id": 42,
  "score_wit": { "yuko": 2, "wazaari": 1, "ippon": false, "shido": 0 },
  "score_blauw": { "yuko": 0, "wazaari": 0, "ippon": false, "shido": 2 },
  "uitslag_type": "wazaari",
  "match_duration_actual": 187,
  "golden_score": false,
  "updated_at": "2026-03-21T14:30:00Z"
}
```

---

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

## Reverb Events

### ScoreboardAssignment (Server → Scorebord App)
**Channel:** `scoreboard.{toernooiId}.{matId}`
**Trigger:** Wanneer wedstrijd op groen gezet in mat interface
**Payload:**
```json
{
  "wedstrijd_id": 101,
  "judoka_wit": { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord" },
  "judoka_blauw": { "id": 43, "naam": "Piet Jansen", "club": "Sportclub Oost" },
  "poule_naam": "B-pupillen -34 kg",
  "ronde": null,
  "match_duration": 240
}
```

### ScoreboardEvent (Bediening → Display via Server)
**Channel:** `scoreboard-display.{toernooiId}.{matId}`
**Trigger:** Bediening POST naar `/api/scoreboard/event`
**Payload:** Verschilt per event type:

**timer.start:**
```json
{ "event": "timer.start", "timestamp": 1711025400.123, "remaining": 240, "golden_score": false }
```

**timer.stop:**
```json
{ "event": "timer.stop", "remaining": 134.5 }
```

**timer.reset:**
```json
{ "event": "timer.reset", "duration": 240 }
```

**score.update:**
```json
{
  "event": "score.update",
  "scores": {
    "wit": { "yuko": 1, "wazaari": 1, "ippon": false, "shido": 0 },
    "blauw": { "yuko": 0, "wazaari": 0, "ippon": false, "shido": 1 }
  }
}
```

**osaekomi.start:**
```json
{ "event": "osaekomi.start", "judoka": "blauw", "timestamp": 1711025400.123 }
```

**osaekomi.stop:**
```json
{ "event": "osaekomi.stop" }
```

**match.start:**
```json
{
  "event": "match.start",
  "wedstrijd_id": 101,
  "judoka_wit": { "id": 42, "naam": "Jan de Vries", "club": "Judo Club Noord" },
  "judoka_blauw": { "id": 43, "naam": "Piet Jansen", "club": "Sportclub Oost" },
  "poule_naam": "B-pupillen -34 kg",
  "match_duration": 240
}
```

**match.end:**
```json
{ "event": "match.end", "winner": "wit", "uitslag_type": "wazaari" }
```

---

## Laravel Bestanden

### Nieuw
| Bestand | Doel |
|---------|------|
| `routes/api.php` | Scoreboard API routes |
| `app/Http/Controllers/Api/ScoreboardController.php` | 5 endpoints |
| `app/Http/Middleware/CheckScoreboardToken.php` | Bearer token auth |
| `app/Events/ScoreboardAssignment.php` | Wedstrijd toewijzing event |
| `app/Events/ScoreboardEvent.php` | Event-based sync naar display |
| `database/migrations/xxxx_add_scoreboard_to_device_toegangen.php` | api_token kolom |
| `resources/views/pages/mat/scoreboard-live.blade.php` | Web display (Blade + Reverb) |

### Gewijzigd
| Bestand | Wijziging |
|---------|-----------|
| `MatController.php` | Dispatch ScoreboardAssignment bij groen zetten |
| `DeviceToegang.php` | api_token field, scoreboard rol |
| `routes/channels.php` | Nieuwe WebSocket channels |
| `routes/web.php` | Route voor scoreboard-live display |
| `bootstrap/app.php` | API middleware registreren |

---

## App Vereisten

### Background service
- Timer MOET doorlopen als app geminimaliseerd wordt (foreground service)
- Sticky notification toont lopende tijd
- Android permissions: `WAKE_LOCK`, `FOREGROUND_SERVICE`, `VIBRATE`

### Offline resilience
- Timer draait ALTIJD door (geen netwerk nodig)
- Uitslag ge-queued als offline → automatisch verstuurd bij reconnect
- Keep-awake tijdens actieve wedstrijd

### Distributie
- APK via eigen server (sideloading, geen Play Store)
- OTA updates voor kleine fixes
- Version check endpoint: `GET /api/scoreboard/version`

## Openstaand

- **LCD proporties voor TV (3-10m leesbaarheid):** timer ~15-20vw, grotere cijfers Y/W/I, namen op afstand leesbaar, shido-kaarten groter, vw/vh units voor 1920x1080.
- **LCD link in device-toegangen updaten** naar `/tv/{eerste 4 tekens}` formaat via `DeviceToegang::getDisplayCode()`.

---

*Laatst bijgewerkt: 21 april 2026*

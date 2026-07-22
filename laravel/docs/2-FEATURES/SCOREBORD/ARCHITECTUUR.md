---
title: Technische architectuur
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Technische architectuur

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

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
| `osaekomi.warning` | 2e waza-ari tijdens osaekomi (awasete-ippon): balk aan/uit | Bij trigger + toketa |
| `match.end` | Winnaar bepaald | 1x |

**Vertraging:** ~200ms bij score wijziging — onmerkbaar voor publiek.

### Authenticatie

- Hergebruikt `DeviceToegang` systeem met nieuwe rol `scoreboard`
- App logt in met de **12-teken code** → ontvangt Bearer **api_token**.
  De pincode is verwijderd (migratie `2026_04_10_120000`): hij voegde geen entropie
  toe bovenop de code (~62 bits) en kostte alleen tikwerk aan de tafel.
- Token opgeslagen in **SecureStore** (Android Keystore) aan app-zijde, meegestuurd als
  `Authorization: Bearer {token}`
- Geen CSRF nodig (API routes buiten web middleware)

### Autorisatie — wat een token mag (security-review 15 jul 2026)

Het token is gebonden aan **één toernooi en één mat** (`device_toegangen.toernooi_id`
+ `mat_nummer`). Dat is geen administratief detail maar de tenant-grens:

- `CheckScoreboardToken` zet het device op **`$request->attributes`**, bewust niet via
  `$request->merge()`. Merge schrijft in de input-bag, waardoor het model in
  `$request->all()` opdook en zijn `api_token` meelekte in de publieke broadcast van
  `/event`. Zet het hier nooit terug naar `merge()`.
- `DeviceToegang` heeft `$hidden = ['api_token', 'device_token', 'code']` als vangnet
  voor toekomstige serialisatie.
- `result()` controleert dat de wedstrijd tot `$toegang->toernooi_id` behoort en geeft
  anders **404** (isolatie-conventie, net als `ClubSyncController`). Zonder die check kon
  elk scorebord-token de uitslag van elk toernooi in het systeem zetten.
- Beschermde routes draaien op `throttle:scoreboard` = **120/min per token**, niet per IP:
  alle matten in een zaal delen één NAT-IP.

### Toegang intrekken (Reset)

**Reset** bij Instellingen → Device Toegangen trekt de toegang écht in:
`api_token` weg (het apparaat kan niets meer), device losgekoppeld, én **een nieuwe code**.
De oude code is daarna waardeloos — geef de mat de nieuwe code van het scherm.
"Alle toegangen intrekken" doet dit voor het hele toernooi.

> Tot 15 jul 2026 nulde Reset alleen de device-binding en **liet het `api_token` staan** — een
> "gereset" apparaat schreef gewoon door. Zet dat niet terug: `reset()` moet alle drie
> (token, binding, code) doen, anders is intrekken een illusie.

Publieke Reverb-kanalen zijn een bewuste keuze (Henk, 15 jul): meeluisteren kan als je de URL
kent, maar de data is wat in de zaal op het scherm staat. Zie
HavunCore `docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`.

### API Endpoints

```
POST /api/scoreboard/auth            → Login: 12-teken code → token + config
GET  /api/scoreboard/current-match   → Huidige wedstrijd ophalen (alleen bij (re)connect)
GET  /api/scoreboard/green-check     → Is deze wedstrijd nog groen (aan de beurt)? (vóór start)
POST /api/scoreboard/result          → Uitslag terugsturen (alleen de groene wedstrijd)
POST /api/scoreboard/event           → Sync event naar display (event-based, niet continu)
POST /api/scoreboard/heartbeat       → Verbinding alive houden
POST /api/scoreboard/tv-link         → TV koppelen via QR (body: { code }) — mat uit token
```

#### POST /api/scoreboard/auth
**Request:**
```json
{
  "code": "ABC123DEF456"
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

#### GET /api/scoreboard/green-check?wedstrijd_id={id}

Vraagt of `wedstrijd_id` de **groene** (actieve) wedstrijd is op de mat van dit token. De app
roept dit async aan bij de eerste timer-start; bij `groen:false` stopt de app de timer en toont
een melding + "wissel"-knop. Bij `groen:false` komt de **nieuwe groene match** meteen mee (zelfde
`formatMatch` als current-match), zodat de app geen tweede call nodig heeft.

**Responses (200):**
```json
// groen
{ "groen": true, "actieve_wedstrijd_id": 1234 }

// niet groen — nieuwe groene match meegestuurd
{ "groen": false, "actieve_wedstrijd_id": 1290, "reden": "niet_groen",
  "match": { "id": 1290, "judoka_wit": {…}, "judoka_blauw": {…}, "poule_naam": "…", … } }

// geen actieve wedstrijd op de mat
{ "groen": false, "actieve_wedstrijd_id": null, "reden": "geen_actieve_wedstrijd", "match": null }
```
`wedstrijd_id` van een ander toernooi → **404** (tenant-isolatie, net als result()).

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

`updated_at` is optioneel (optimistic locking); laat je het weg, dan wint de laatste schrijver.

**Groen-gate:** de app mag alléén de **groene** (actieve) wedstrijd van zijn mat scoren. Een
niet-groene POST (verschoven beurt / late offline-flush) wordt geweigerd vóór enige write —
anders zou de bracket-doorschuif voor een verkeerde wedstrijd draaien. Correcties van afgeronde
wedstrijden lopen via het web (`MatUitslagController`), niet via deze endpoint.
Zie `.claude/plan-scoreboard-groen-gate.md`.

**Responses:**

| Status | Body-signaal | Wanneer |
|--------|--------------|---------|
| 200 | `success: true` | Uitslag verwerkt |
| 400 | `message` | `winnaar_id` is geen deelnemer van deze wedstrijd |
| 404 | `message` | Wedstrijd bestaat niet **of hoort niet bij het toernooi van je token** (tenant-isolatie) |
| 409 | `error: "niet_groen"` + `actieve_wedstrijd_id` | Wedstrijd is niet (meer) de groene → **permanent**, niet queuen/herproberen |
| 409 | `success: false` + `server_updated_at` | `updated_at` wijkt af — een ander apparaat was je voor |
| 429 | — | Meer dan 120 requests/min met dit token |

> Twee 409-varianten, te onderscheiden op de body: `error: "niet_groen"` (beurt verschoven,
> permanent) vs. `server_updated_at` (optimistic-lock). De groen-gate draait het eerst.

---


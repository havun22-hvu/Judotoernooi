# Scorebord App — Standalone Android App

> **Status:** In ontwikkeling (maart 2026)
> **Type:** Expo React Native (TypeScript) — apart project
> **Locatie:** `D:\GitHub\JudoScoreboard\`
> **Doel:** Judo scorebord op scheidsrechter-tablet, gekoppeld aan JudoToernooi wedstrijdsysteem

## Overzicht

Standalone Android app voor het bijhouden van judo wedstrijden (timer, scores, shido's, osaekomi).
Verbonden met JudoToernooi via API — ontvangt wedstrijden en stuurt uitslagen automatisch terug.

### Twee views

| View | Wie | Functie |
|------|-----|---------|
| **Bediening** | Tafelofficial (tablet) | Alle knoppen: timer, score, shido, osaekomi |
| **Display** | Scheidsrechter/publiek (TV/scherm) | Zelfde visueel, geen knoppen, **gespiegeld** |

### Spiegeling (IJF standaard)

De bediening en display zijn **gespiegeld** — de tafelofficial kijkt naar de mat,
de scheidsrechter kijkt naar de tafel. Wat links is voor de een, is rechts voor de ander.

| View | Links | Rechts |
|------|-------|--------|
| **Bediening** (tafelofficial) | Blauw | Wit |
| **Display** (scheidsrechter) | Wit | Blauw |

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

## Technische Architectuur

### Communicatie

| Richting | Methode | Channel/Endpoint |
|----------|---------|-----------------|
| Mat → Scorebord | Reverb WebSocket | `scoreboard.{toernooiId}.{matId}` |
| Scorebord → Mat | REST API POST | `POST /api/scoreboard/result` |
| Control → Display | Reverb WebSocket (via server) | `scoreboard-display.{toernooiId}.{matId}` |
| Polling fallback | REST API GET | `GET /api/scoreboard/current-match` |

### Authenticatie

- Hergebruikt `DeviceToegang` systeem met nieuwe rol `scoreboard`
- App logt in met **code + pincode** → ontvangt Bearer **api_token**
- Token opgeslagen in AsyncStorage, meegestuurd als `Authorization: Bearer {token}`
- Geen CSRF nodig (API routes buiten web middleware)

### API Endpoints

```
POST /api/scoreboard/auth            → Login: code + pincode → token + config
GET  /api/scoreboard/current-match   → Huidige wedstrijd ophalen (polling)
POST /api/scoreboard/result          → Uitslag terugsturen
POST /api/scoreboard/state           → Live state broadcasten naar display
POST /api/scoreboard/heartbeat       → Verbinding alive houden
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
  "score_wit": { "wazaari": 1, "ippon": false, "shido": 0 },
  "score_blauw": { "wazaari": 0, "ippon": false, "shido": 2 },
  "uitslag_type": "wazaari",
  "match_duration_actual": 187,
  "golden_score": false,
  "updated_at": "2026-03-21T14:30:00Z"
}
```

---

## Scoring Regels (IJF 2024)

| Actie | Regel |
|-------|-------|
| **Waza-ari** | Score cycling: 0 → W → IPPON (2 waza-ari = ippon) |
| **Shido** | 3 kaartjes per judoka, 3e = hansoku-make = ippon tegenstander |
| **Osaekomi** | 10 seconden = waza-ari, 20 seconden = ippon |
| **Golden Score** | Bij gelijkspel na tijd, timer telt omhoog |
| **Timer** | Instelbaar 2-5 minuten, rood bij laatste 30 seconden |

## Geluiden

| Event | Geluid |
|-------|--------|
| Match einde | Triple beep (880Hz) |
| Osaekomi milestone (10s) | Single low beep (440Hz) |
| Ippon | Victory fanfare (C-E-G-C) |
| 30 seconden warning | Timer kleurt rood |

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

### ScoreboardState (Server → Display View)
**Channel:** `scoreboard-display.{toernooiId}.{matId}`
**Trigger:** Control view POST naar `/api/scoreboard/state`
**Payload:**
```json
{
  "timer": { "remaining": 134.5, "running": true, "golden_score": false },
  "scores": {
    "wit": { "wazaari": 1, "ippon": false, "shido": 0 },
    "blauw": { "wazaari": 0, "ippon": false, "shido": 1 }
  },
  "osaekomi": { "active": false, "judoka": null, "time": 0 },
  "winner": null
}
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
| `app/Events/ScoreboardState.php` | Live state broadcast event |
| `database/migrations/xxxx_add_scoreboard_to_device_toegangen.php` | api_token kolom |

### Gewijzigd
| Bestand | Wijziging |
|---------|-----------|
| `MatController.php` | Dispatch ScoreboardAssignment bij groen zetten |
| `DeviceToegang.php` | api_token field, scoreboard rol |
| `routes/channels.php` | Nieuwe WebSocket channels |
| `bootstrap/app.php` | API middleware registreren |

---

## Spiegeling in de App (Belangrijk!)

De Display view toont het scorebord **gespiegeld** ten opzichte van de Bediening.
Dit is IJF standaard: de tafelofficial kijkt naar de mat, de scheidsrechter kijkt
naar de tafel — wat links is voor de een, is rechts voor de ander.

**Implementatie in de app:**
- `ControlScreen.tsx`: Blauw links, Wit rechts
- `DisplayScreen.tsx`: Wit links, Blauw rechts (mirrored)
- De data is identiek, alleen de visuele positie wisselt

---

*Laatst bijgewerkt: 21 maart 2026*

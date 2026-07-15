---
title: Voorbeelden (cURL) & WebSocket-events
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Voorbeelden (cURL) & WebSocket-events

> Onderdeel van [API-referentie](../API.md).

## Voorbeelden (cURL)

### Toernooi statistieken ophalen

```bash
curl -X GET "https://judotournament.org/api/v1/toernooi/1/statistieken" \
  -H "Accept: application/json"
```

### Judoka zoeken

```bash
curl -X POST "https://judotournament.org/toernooi/1/judoka/zoek?q=Jan" \
  -H "Accept: application/json" \
  -H "X-Requested-With: XMLHttpRequest"
```

### Gewicht registreren

```bash
curl -X POST "https://judotournament.org/toernooi/1/weging/42/registreer" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{"gewicht": 33.5}'
```

### Uitslag registreren

```bash
curl -X POST "https://judotournament.org/toernooi/1/mat/uitslag" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{
    "wedstrijd_id": 101,
    "winnaar_id": 42,
    "score_wit": "Ippon",
    "score_blauw": "",
    "uitslag_type": "ippon"
  }'
```

---

## WebSocket Events (Reverb)

De applicatie gebruikt Laravel Reverb voor real-time updates.

### Kanalen

| Kanaal | Beschrijving |
|--------|--------------|
| `toernooi.{id}` | Toernooi-brede events |
| `mat.{id}` | Mat-specifieke updates |
| `spreker.{id}` | Spreker interface updates |

### Events

| Event | Payload |
|-------|---------|
| `WedstrijdGespeeld` | `{ wedstrijd_id, winnaar_id, poule_id }` |
| `PouleKlaar` | `{ poule_id, mat_id }` |
| `PouleAfgeroepen` | `{ poule_id }` |
| `WegingGeregistreerd` | `{ judoka_id, gewicht }` |

---

*Laatste update: Februari 2026*

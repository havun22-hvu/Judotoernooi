---
title: Plan — "mijn toernooien"-lijst strikt op eigenaar scopen
type: plan
scope: judotoernooi
last_updated: 2026-07-21
status: in-uitvoering
---

# Plan — mijn toernooien alleen eigen (rol=eigenaar)

**Klacht (Henk, 21-07):** ziet "soms" een toernooi van een andere organisator (Jeroens
"test toernooi bsh") in zijn eigen "mijn toernooien"-lijst. Wens: de lijst toont **alleen
zelf-aangemaakte toernooien**; andere toernooien blijven bereikbaar via het admin-dashboard.
Contract: `CONTRACTS.md → C-01` (multi-tenant isolatie).

## Diagnose

- De lijst (`OrganisatorDashboardController::organisatorDashboard`, regel 57) draait op de
  BelongsToMany pivot `organisator_toernooi` en is al org-gescoped, maar toont **elke rol**,
  niet alleen `eigenaar`. Een spurious/tweede pivot-rij lekt dus direct in de lijst.
- Explore-sweep (21-07): **geen** accessor/getter/observer schrijft als side-effect een
  pivot-rij; de pivot is in rust schoon (prod: 2 rijen, allemaal `eigenaar`). Wat Henk "soms"
  zag is hoogstwaarschijnlijk een historische spookrij die er niet meer is.
- Latente C-01-zwakte: `ToernooiService::getActiefToernooi()` (regel 280) =
  `Toernooi::actief()->first()` → globaal actief toernooi, **geen** org-scope. Nu alleen via
  dode/niet-gerouteerde code bereikbaar (legacy `dashboard()`, `ToernooiApiController::actief`),
  maar een tijdbom zodra iemand het rout. `is_actief` staat bovendien op meerdere toernooien
  tegelijk (default `true` bij aanmaken, regel 37).

## Fix (robuust — waterdicht, ongeacht bron spookrij)

1. **Lijst** `organisatorDashboard:57` → `->wherePivot('rol', 'eigenaar')`. Toont gegarandeerd
   alleen zelf-aangemaakte toernooien. Sluit elke niet-eigenaar-koppeling uit.
2. **`getActiefToernooi()`** org-scopen: parameter `?Organisator`, val terug op
   `auth('organisator')->user()`, filter via pivot `rol=eigenaar` + `is_actief`. Veilig by
   default; dode callers blijven werken.
3. **Test** (`TenantScopeTest`): organisator A ziet in de dashboard-lijst zijn eigen toernooi
   wél, en een vreemd toernooi (van B) níét — óók niet met een spurious niet-eigenaar
   pivot-rij A↔B.

## Verificatie
- `php vendor/bin/phpunit --filter TenantScope --no-coverage` lokaal (SQLite). Nooit op server.
- Staging: Henk opent "mijn toernooien" → alleen Generale + test2 (org#1), geen bsh.

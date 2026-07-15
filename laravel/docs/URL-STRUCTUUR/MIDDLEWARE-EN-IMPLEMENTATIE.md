---
title: Route-middleware en implementatie-notities
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Route-middleware en implementatie-notities

> Onderdeel van [URL Structuur](../URL-STRUCTUUR.md).

## Route Middleware Samenvatting

| Route Pattern | Middleware | Auth Type |
|---------------|------------|-----------|
| `/`, `/login`, `/registreren` | `web` | Geen |
| `/admin` | `auth:organisator` + sitebeheerder check | Organisator + rol |
| `/{org}/dashboard`, `/{org}/clubs`, etc. | `auth:organisator` | Organisator |
| `/{org}/toernooi/{toernooi}/*` | `auth:organisator` | Organisator |
| `/{org}/{toernooi}` | `web` | Geen |
| `/{org}/{toernooi}/school/{code}/*` | `web` | PIN (in controller) |
| `/{org}/{toernooi}/toegang/{code}` | `web` | PIN (in controller) |
| `/{org}/{toernooi}/*/{toegang}` | `device.binding` | PIN + Device |
| `/weegkaart/{token}` | `web` | Token (in URL) |
| `/coach-kaart/{qrCode}` | `web` | QR code (in URL) |

---

## Implementatie Notities

### Route Volgorde (Belangrijk!)

Laravel matcht routes in volgorde. Specifiekere routes moeten VOOR algemenere routes:

```php
// EERST: Specifieke routes
Route::get('{org}/toernooi/{toernooi}', ...);     // beheer
Route::get('{org}/{toernooi}/school/{code}', ...); // coach portal

// LAATST: Algemene "catch-all" publieke route
Route::get('{org}/{toernooi}', ...);              // publiek
```

### Slug Constraints

Voorkom conflicten met reserved words:

```php
Route::get('{org}/{toernooi}', ...)
    ->where('org', '^(?!admin|login|registreren|weegkaart|coach-kaart).*$')
    ->where('toernooi', '^(?!dashboard|clubs|templates|presets|toernooi).*$');
```

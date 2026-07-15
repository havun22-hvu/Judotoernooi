---
title: TV/LCD URLs & de mat-rij in Device Toegangen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# TV/LCD URLs & de mat-rij in Device Toegangen

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

## TV/LCD URLs (productie)

Het LCD-display is via twee URLs bereikbaar — device-toegangen toont beide (knoppen "Kort" + "Volledig"):

| Type | URL | Werking |
|------|-----|---------|
| **Origineel (volledig)** | `judotournament.org/{org}/{toernooi}/mat/scoreboard-live/{matNummer}` | `MatController::scoreboardLive` → view. Werkt altijd, lange URL. |
| **Kort** | `havun.nl/tv/{4-char code}` → `judotournament.org/tv/{code}` → `MatController::tvRedirect` → scoreboard-live | Makkelijk over te typen op een TV. |

**havun.nl/tv redirect (nginx, server 188.245.159.115).** `havun.nl` is een ander project op
dezelfde server. De redirect staat in `/etc/nginx/sites-enabled/havun.nl`:
```nginx
location = /tv  { return 301 https://judotournament.org/tv; }          # bare
location = /tvs { return 301 https://staging.judotournament.org/tv; }  # bare staging
location ~ ^/tv/(.+)$  { return 301 https://judotournament.org/tv/$1; }          # /tv/{code}
location ~ ^/tvs/(.+)$ { return 301 https://staging.judotournament.org/tv/$1; }  # staging /tvs/{code}
```
> **Was kapot (juni 2026):** alleen de exact-match `location = /tv` bestond → `havun.nl/tv/JTCI`
> viel door naar de Node-proxy → 404. Opgelost met de regex-locations die de code (`$1`) meenemen.
> Bij wijziging van het pad-formaat: deze nginx-regels mee aanpassen.

### Mat-rij in Instellingen → Device Toegangen

Elke mat-rij toont links de codes en rechts de knoppen. Beide kolommen houden **dezelfde volgorde**:
Mat interface boven, LCD eronder.

| Rij | Links (code) | Rechts (knoppen) |
|-----|--------------|------------------|
| **Mat interface** | volledige 12-teken code + kopieerknop | URL · QR · Reset |
| **LCD** | eerste 4 tekens (de `havun.nl/tv`-code) | Kort · Volledig · Koppel TV |

**Geen QR bij LCD.** Een QR is bedoeld om met een camera te scannen; een TV heeft er geen. De TV
koppel je met de 4-cijferige code ("Koppel TV") of door de korte URL over te typen. De QR hoort
dus alleen bij Mat interface — die scan je wél, met de scorebord-app of tablet.

CSP: `scoreboard-live.blade.php` draait onder strikte CSP — alle `<script>`/`<style>` met `@nonce`,
knoppen via `data-action`, Pusher-CDN met `integrity`+`@nonce`. De `?.`/`??` in die view staan in
**vanilla JS** (geen Alpine-expressies) → CSP-veilig.


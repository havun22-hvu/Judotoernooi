# Chromecast (Google Cast) Integratie

> LCD-scherm URL naar TV casten via Chromecast

## Status

**In ontwikkeling** — SDK CSP-fix gedeployed (2026-04-06), wacht op functionele test.

## Hoe het werkt

1. **Sender** (browser op beheer-PC) laadt Google Cast SDK
2. Gebruiker klikt "Cast naar TV" knop in Organisatie-instellingen
3. SDK opent Cast-dialoog → kies Chromecast device
4. **Receiver** (Custom Receiver op Chromecast) ontvangt URL via custom namespace
5. Receiver laadt URL in fullscreen iframe → scorebord op TV

## Technische details

| Component | Bestand |
|-----------|---------|
| Cast knop + Sender SDK | `resources/views/pages/toernooi/partials/device-toegangen.blade.php` |
| Custom Receiver | `resources/views/pages/cast/receiver.blade.php` |
| Receiver route | `routes/web.php` → `/cast/receiver` |
| CSP config | `app/Http/Middleware/SecurityHeaders.php` |

### Application ID

- **ID:** `C11C3563` (unpublished, test device geregistreerd in Google Cast Developer Console)
- **Custom namespace:** `urn:x-cast:judotoernooi`

### CSP vereisten

`www.gstatic.com` moet in zowel `script-src` als `connect-src` staan. Zonder dit wordt de Cast SDK stil geblokkeerd door de browser.

## Bekende issues

| Datum | Issue | Status |
|-------|-------|--------|
| 2026-04-06 | `__onGCastApiAvailable` werd nooit aangeroepen — CSP blokkeerde `cast_sender.js` | Opgelost |

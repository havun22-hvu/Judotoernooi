---
title: Coachkaarten: time-based QR
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coachkaarten: time-based QR

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

### Time-based QR Codes (Screenshot Beveiliging)

Coachkaart QR-codes zijn **tijdgebonden** om screenshot-fraude te voorkomen:

| Setting | Waarde | Beschrijving |
|---------|--------|--------------|
| `QR_VALID_MINUTES` | 5 min | Maximale leeftijd voor geldige scan |
| `QR_REFRESH_MINUTES` | 4 min | QR ververst automatisch na 4 minuten |

**Werking:**
1. QR-code bevat URL met timestamp (`t`) en HMAC signature (`s`)
2. Client-side JavaScript genereert elke 4 minuten nieuwe QR
3. Bij scan: server valideert signature en controleert leeftijd (max 5 min)
4. Verlopen QR → oranje "QR-CODE VERLOPEN" melding

**URL formaat:**
```
/coach-kaart/{qrCode}/scan?t={timestamp}&s={signature}
```

**Signature generatie (beide client + server):**
```
HMAC-SHA256(qrCode + '|' + timestamp, APP_KEY).substring(0, 16)
```

**Timer weergave:**
- Laatste 60 seconden: toont "Ververst over Xs" in oranje
- Na 4 minuten: QR wordt automatisch ververst
- Bij tab-switch: QR wordt direct ververst (visibilitychange event)

**Bestanden:**
- `CoachKaart::generateScanSignature()` - Server-side signature
- `CoachKaart::validateScanToken()` - Server-side validatie
- `show.blade.php` - Client-side QR generatie met Web Crypto API
- `scan-result.blade.php` - Toont "verlopen" melding bij `$tokenExpired`


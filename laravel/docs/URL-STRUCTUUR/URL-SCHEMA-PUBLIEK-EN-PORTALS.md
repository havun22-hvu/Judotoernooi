---
title: URL-schema: publieke pagina's, coach portal en vrijwilligers
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# URL-schema: publieke pagina's, coach portal en vrijwilligers

> Onderdeel van [URL Structuur](../URL-STRUCTUUR.md).

### 5. Publieke Pagina's

**Auth: Geen**

Vrij toegankelijk voor iedereen (bezoekers, ouders).

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}` | Publieke toernooi pagina (PWA) |
| `/weegkaart/{token}` | Weegkaart via QR code |
| `/coach-kaart/{qrCode}` | Coach kaart via QR code |

**Voorbeeld:** `/judo-ceesveen/open-2026`

> **Let op:** Geen `toernooi` in de URL = publiek toegankelijk

#### Verschil publiek vs beheer:

```
PUBLIEK:  /{org}/{toernooi}
          /judo-ceesveen/open-2026
                        ↑ direct na org-slug

BEHEER:   /{org}/toernooi/{toernooi}
          /judo-ceesveen/toernooi/open-2026
                        ↑ woord "toernooi" ertussen
```

---

### 6. Coach Portal

**Auth: Rolcode**

Coaches krijgen een link met een 12-char rolcode.

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/school/{code}` | Login pagina |
| `/{org}/{toernooi}/school/{code}/judokas` | Judoka's beheren |
| `/{org}/{toernooi}/school/{code}/weegkaarten` | Weegkaarten bekijken |
| `/{org}/{toernooi}/school/{code}/coachkaarten` | Coach kaarten |
| `/{org}/{toernooi}/school/{code}/resultaten` | Resultaten |
| `/{org}/{toernooi}/school/{code}/afrekenen` | Betalen |

**Voorbeeld:** `/judo-ceesveen/open-2026/school/ABC123`

---

### 7. Vrijwilligers (Device Binding)

**Auth: Rolcode + Device binding**

Vrijwilligers krijgen een toegangslink met 12-char rolcode; hun browser/device wordt gekoppeld via `CheckDeviceBinding` middleware.

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/toegang/{code}` | Toegang link + PIN invoer |
| `/{org}/{toernooi}/weging/{toegang}` | Weging interface |
| `/{org}/{toernooi}/mat/{toegang}` | Mat interface |
| `/{org}/{toernooi}/jury/{toegang}` | Jury interface |
| `/{org}/{toernooi}/spreker/{toegang}` | Spreker interface |
| `/{org}/{toernooi}/dojo/{toegang}` | Dojo scanner |

**Voorbeeld:** `/judo-ceesveen/open-2026/toegang/XYZ789`

---


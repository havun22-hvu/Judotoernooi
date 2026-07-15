---
title: URL-schema: QR-codes, dojo scanner API en legacy redirects
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# URL-schema: QR-codes, dojo scanner API en legacy redirects

> Onderdeel van [URL Structuur](../URL-STRUCTUUR.md).

### 8. QR Code URLs

Korte URLs voor QR codes (token = authenticatie).

| URL | Doel | Gegenereerd voor |
|-----|------|------------------|
| `/weegkaart/{token}` | Weegkaart tonen | Elke judoka |
| `/coach-kaart/{qrCode}` | Coach kaart + check-in | Elke coach |

> **Waarom kort?** Kortere URL = kleinere QR code = makkelijker scannen

---

### 9. Dojo Scanner API

**Auth: Geen (interne API voor dojo scanner interface)**

| URL | Doel |
|-----|------|
| `/{org}/{toernooi}/dojo/clubs` | Lijst clubs met coach kaarten |
| `/{org}/{toernooi}/dojo/club/{club}` | Detail van club coach kaarten |

> **Gebruikt door:** Dojo scanner interface (vrijwilligers device)

---

### 10. Legacy Redirects

Oude URLs die doorverwijzen naar nieuwe structuur.

| Oude URL | Nieuwe URL |
|----------|------------|
| `/school/{code}` | `/{org}/{toernooi}/school/{code}` |
| `/toegang/{code}` | `/{org}/{toernooi}/toegang/{code}` |
| `/organisator/dashboard` | `/{org}/dashboard` |
| `/organisator/login` | `/login` |
| `/toernooi/{toernooi}` | `/{org}/toernooi/{toernooi}` |

---


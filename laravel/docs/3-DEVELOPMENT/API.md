---
title: API-referentie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# API-referentie

> Authenticatie, headers en rate limiting staan hieronder — die heb je bij elke call nodig.
> De endpoints zelf staan per groep in [`API/`](API/). **Index-doc**, zie de wegwijzer onderaan.

## Authenticatie

### Publieke Endpoints
De volgende endpoints zijn publiek toegankelijk (geen authenticatie vereist):
- `/api/v1/toernooi/*` - Toernooi informatie
- `/{organisator}/{toernooi}/*` - Publieke toernooi views

### Organisator Endpoints
Routes onder `/organisator/*` vereisen sessie-authenticatie via Laravel.

```bash
# Login verkrijgen
POST /organisator/login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=secret&_token=CSRF_TOKEN
```

### CSRF Protection
Alle POST/PUT/DELETE requests naar web routes vereisen een CSRF token.

**Uitgezonderd van CSRF:**
- `/{organisator}/{toernooi}/favorieten` - Publieke favorites API
- `/{organisator}/{toernooi}/scan-qr` - Publieke QR scan
- `/mollie/webhook` - Mollie webhook callbacks

---

## Request/Response Headers

### Request Headers
| Header | Waarde | Verplicht |
|--------|--------|-----------|
| `Content-Type` | `application/json` | Ja (voor JSON body) |
| `Accept` | `application/json` | Aanbevolen |
| `X-Requested-With` | `XMLHttpRequest` | Voor AJAX requests |

### Response Headers
| Header | Beschrijving |
|--------|--------------|
| `X-Frame-Options` | `SAMEORIGIN` - Clickjacking bescherming |
| `X-Content-Type-Options` | `nosniff` - MIME sniffing bescherming |
| `X-XSS-Protection` | `1; mode=block` - XSS filter |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | CSP headers (productie) |
| `Strict-Transport-Security` | HSTS (productie, HTTPS) |

---

## Rate Limiting

| Endpoint Type | Limiet | Window |
|---------------|--------|--------|
| API requests | 60 requests | per minuut |
| Login attempts | 5 attempts | per minuut |
| QR scan | 30 requests | per minuut |

Bij overschrijding: HTTP `429 Too Many Requests`

```json
{
  "message": "Too Many Attempts.",
  "retry_after": 45
}
```

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [ENDPOINTS-TOERNOOI](API/ENDPOINTS-TOERNOOI.md) | Toernooi- en judoka-endpoints. |
| [ENDPOINTS-WEGING](API/ENDPOINTS-WEGING.md) | Weging: scannen, gewicht doorgeven. |
| [ENDPOINTS-MAT](API/ENDPOINTS-MAT.md) | Mat-interface: uitslagen, selecties. |
| [ENDPOINTS-SPREKER](API/ENDPOINTS-SPREKER.md) | Spreker + organisator-routes (auth vereist). |
| [ERROR-HANDLING](API/ERROR-HANDLING.md) | Welke foutcodes je terugkrijgt en wat ze betekenen. |
| [VOORBEELDEN](API/VOORBEELDEN.md) | Werkende cURL-calls en de Reverb WebSocket-events. |

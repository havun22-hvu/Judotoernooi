---
title: Error handling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Error handling

> Onderdeel van [API-referentie](../API.md).

## Error Handling

### HTTP Status Codes

| Code | Beschrijving |
|------|--------------|
| `200` | OK - Request succesvol |
| `201` | Created - Resource aangemaakt |
| `400` | Bad Request - Validatie fout |
| `401` | Unauthorized - Niet ingelogd |
| `403` | Forbidden - Geen toegang |
| `404` | Not Found - Resource niet gevonden |
| `419` | Page Expired - CSRF token verlopen |
| `422` | Unprocessable Entity - Validatie errors |
| `429` | Too Many Requests - Rate limit bereikt |
| `500` | Internal Server Error - Server fout |

### Error Response Format

```json
{
  "success": false,
  "message": "Beschrijving van de fout",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "gewicht": ["Het gewicht veld is verplicht."]
  }
}
```

### Custom Error Codes

| Code | Beschrijving |
|------|--------------|
| `VALIDATION_ERROR` | Input validatie gefaald |
| `MOLLIE_ERROR` | Betaling fout |
| `IMPORT_ERROR` | Import fout |
| `EXTERNAL_SERVICE_ERROR` | Externe service niet beschikbaar |

---


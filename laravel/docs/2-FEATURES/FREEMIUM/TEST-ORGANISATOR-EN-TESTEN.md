---
title: Test-organisator & testen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Test-organisator & testen

> Onderdeel van [Freemium Model](../FREEMIUM.md).

## Test Organisator

Organisatoren kunnen als "test" gemarkeerd worden (`is_test = true`). Dit bypassed betalingen volledig:

### Gedrag test organisator
- **Geen Mollie redirect** - directe upgrade
- **Geen kosten** - bedrag = €0
- **Alle environments** - werkt op local, staging, en production
- **Direct actief** - `ToernooiBetaling` wordt aangemaakt met status `paid`

### Database
```sql
-- organisators tabel
is_test    BOOLEAN DEFAULT FALSE
```

### Gebruiksscenario
Test organisator (zoals "Judoschool Cees Veen") voor:
- Feature testing zonder echte betalingen
- Demo accounts voor nieuwe klanten
- Development en staging tests

### Aanmaken
```sql
UPDATE organisators SET is_test = 1 WHERE slug = 'judoschool-cees-veen';
```

---

## Testen

### Lokaal (Simulatie)

```bash
php artisan serve --port=8007
```

1. Maak toernooi aan (gratis)
2. Voeg 50 judoka's toe → OK
3. Voeg 51e toe → Geblokkeerd, upgrade banner
4. Ga naar upgrade pagina
5. Kies staffel, klik betalen
6. Simulatie pagina → kies "Betaald"
7. Toernooi is nu upgraded
8. Voeg meer judoka's toe → OK

### Test Organisator (alle environments)

1. Markeer organisator als test: `is_test = true`
2. Upgrade → Direct geactiveerd, geen Mollie
3. Handig voor demo's en development

### Print Test

1. Free tier → `/noodplan/poules` → Redirect naar upgrade
2. Na upgrade → Print werkt

---


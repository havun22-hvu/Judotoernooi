verkeerde# Handover - JudoToernooi

> Dit bestand wordt bijgewerkt aan het einde van elke sessie.
> Lees dit EERST bij een nieuwe sessie.

## Laatste sessie
**Datum:** 2024-12-21
**Door:** Claude

---

## URGENT: Reset knop 404 fixen

### Probleem
Reset knop op `/toernooi` pagina geeft 404, maar route bestaat WEL (bewezen door 419 CSRF response bij directe POST).

### Waar te debuggen
1. Open F12 -> Network tab
2. Klik Reset knop bij toernooi
3. Check welke URL wordt aangeroepen

### Gewijzigde bestanden
- `app/Http/Controllers/ToernooiController.php` - reset() en destroy() methodes toegevoegd
- `routes/web.php` - reset route in toernooi group
- `resources/views/pages/toernooi/index.blade.php` - knoppen + JavaScript

### JavaScript code (mogelijk probleem)
```javascript
function confirmReset(id, naam) {
    if (confirm(...)) {
        const form = document.getElementById('reset-form');
        form.action = `/toernooi/${id}/reset`;  // <-- check dit!
        form.submit();
    }
}
```

---

## Wat vandaag gedaan

### 1. Toernooi Reset/Delete functionaliteit
- **Start knop** - Gaat naar toernooi dashboard (werkt)
- **Reset knop** - Verwijdert poules/wedstrijden, behoudt judoka's (404 probleem)
- **Delete knop** - Alleen voor sitebeheerder (verborgen tot login werkt)

### 2. SQLite sequence reset
- Fix in `PouleIndelingService.php` - IDs worden gereset bij herindeling
- Database handmatig gereset met PHP script

### 3. Weging interface error
- `aantal_wegingen` null error gefixed met optional chaining

---

## Nog te doen

1. **Reset knop 404 fixen** - Debug met F12 Network tab
2. **Eliminatie bracket testen** - Met schone data na reset
3. **Delete knop activeren** - Na organisator login implementatie

---

## Test commando's

```bash
# Route cache clearen
cd laravel && php artisan route:clear

# Check reset route (moet POST tonen)
php artisan route:list --name=reset

# Direct test (419 = route werkt, 404 = probleem)
curl -X POST http://127.0.0.1:8001/toernooi/1/reset -d "_token=test"
```

---

## Relevante bestanden

| Bestand | Doel |
|---------|------|
| `app/Http/Controllers/ToernooiController.php` | reset() en destroy() methodes |
| `routes/web.php` | Route definitie (regel 70-71) |
| `resources/views/pages/toernooi/index.blade.php` | Knoppen en JavaScript |
| `app/Services/EliminatieService.php` | Bracket generatie |
| `resources/views/pages/mat/interface.blade.php` | Mat interface |

---

## Context

- Laravel server: http://127.0.0.1:8001
- Database: SQLite lokaal
- Toernooi ID 1 = "Open Westfries Judotoernooi"

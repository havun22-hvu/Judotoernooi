# Technische Functies

Dit document beschrijft de technische werking van belangrijke functies/knoppen in het systeem.

---

## Sluit Weegtijd (per blok)

**UI Locatie:** Weging Interface (Admin) → "Blok X: Einde weegtijd" knop

**Wanneer zichtbaar:** Als een blok is geselecteerd in de filter

### Trigger

| Bestand | Methode |
|---------|---------|
| `BlokController.php` | `sluitWeging()` |
| Route | `POST /{organisator}/toernooi/{toernooi}/blok/{blok}/sluit-weging` |

### Wat gebeurt er?

```php
// Blok.php -> sluitWeging()

1. $blok->update([
       'weging_gesloten' => true,
       'weging_gesloten_op' => now()
   ]);

2. $blok->markeerNietGewogenAlsAfwezig();
   // Alle judoka's in dit blok zonder gewicht_gewogen
   // krijgen aanwezigheid = 'afwezig'

3. $blok->herberekenPouleStatistieken();
   // Voor elke poule in dit blok:
   // - Eliminatie poules: verwijder afwezige judoka's uit poule
   // - Alle poules: herbereken statistieken
```

### Flow diagram

```
Knop "Einde weegtijd" klik
         ↓
    Bevestiging?
         ↓
BlokController::sluitWeging()
         ↓
   Blok::sluitWeging()
         ↓
┌────────────────────────────────────┐
│ 1. weging_gesloten = true          │
│ 2. weging_gesloten_op = now()      │
└────────────────────────────────────┘
         ↓
markeerNietGewogenAlsAfwezig()
         ↓
┌────────────────────────────────────┐
│ Judoka's in blok zonder            │
│ gewicht_gewogen → afwezig          │
└────────────────────────────────────┘
         ↓
herberekenPouleStatistieken()
         ↓
┌────────────────────────────────────┐
│ Per poule:                         │
│ - Eliminatie: verwijder afwezigen  │
│ - updateStatistieken()             │
└────────────────────────────────────┘
         ↓
    Redirect met success message
```

### Database wijzigingen

| Tabel | Veld | Wijziging |
|-------|------|-----------|
| `blokken` | `weging_gesloten` | `true` |
| `blokken` | `weging_gesloten_op` | timestamp |
| `judokas` | `aanwezigheid` | `'afwezig'` (voor niet-gewogen) |
| `poule_judoka` | - | DELETE (eliminatie poules, afwezige judoka's) |

### Gevolgen

1. **Countdown stopt** - Weging interface toont geen timer meer
2. **Geen nieuwe wegingen** - Judoka's kunnen niet meer gewogen worden voor dit blok
3. **Afwezigen gemarkeerd** - Niet-gewogen judoka's zijn nu "afwezig"
4. **Eliminatie poules opgeschoond** - Afwezigen verwijderd uit bracket
5. **Wedstrijdschema's** - Afwezige judoka's worden niet meegenomen

### Relevante bestanden

| Bestand | Functie |
|---------|---------|
| `app/Models/Blok.php` | `sluitWeging()`, `markeerNietGewogenAlsAfwezig()`, `herberekenPouleStatistieken()` |
| `app/Http/Controllers/BlokController.php` | `sluitWeging()` route handler |
| `resources/views/pages/weging/interface-admin.blade.php` | UI knop |
| `routes/web.php` | Route definitie |

---

## Noodknop Reset

**UI Locatie:** Dashboard → "Noodknop" (rode knop)

**Toegang:** Alleen sitebeheerder en organisator

### Trigger

| Bestand | Methode |
|---------|---------|
| `ToernooiController.php` | `noodknopReset()` |
| Route | `POST /{organisator}/toernooi/{toernooi}/noodknop-reset` |

### Wat gebeurt er?

```php
// ToernooiController.php -> noodknopReset()

1. Reset alle wedstrijden
   - is_gespeeld = false
   - winnaar_id = null
   - score_wit = null
   - score_blauw = null

2. Reset alle poules
   - actieve_wedstrijd_id = null
   - huidige_wedstrijd_id = null
   - is_klaar = false
   - mat_id = null  // Zaalindeling reset

3. Reset judoka statistieken
   - wp = 0, jp = 0, plaats = null

4. Reset blok weging status
   - weging_gesloten = false
   - weging_gesloten_op = null

5. Reset judoka weging
   - gewicht_gewogen = null
   - aanwezigheid = null
```

### Database wijzigingen

| Tabel | Velden gereset |
|-------|----------------|
| `wedstrijden` | is_gespeeld, winnaar_id, scores |
| `poules` | actieve_wedstrijd_id, huidige_wedstrijd_id, is_klaar, mat_id |
| `poule_judoka` | wp, jp, plaats |
| `blokken` | weging_gesloten, weging_gesloten_op |
| `judokas` | gewicht_gewogen, aanwezigheid |

### Gevolgen

1. **Alle uitslagen weg** - Wedstrijden moeten opnieuw gespeeld
2. **Weging opnieuw** - Alle judoka's moeten opnieuw gewogen
3. **Zaalindeling weg** - Poules moeten opnieuw aan matten toegewezen
4. **Status reset** - Alles terug naar "voorbereiding" staat

---

## Meer functies toevoegen

Voeg hier technische documentatie toe voor andere belangrijke functies zoals:
- Genereer Coachkaarten
- Verdeel over Matten
- Valideer Judoka's
- etc.

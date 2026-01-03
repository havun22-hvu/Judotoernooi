# Handover - JudoToernooi

> Dit bestand wordt bijgewerkt aan het einde van elke sessie.
> Lees dit EERST bij een nieuwe sessie.

## Laatste sessie
**Datum:** 2026-01-03
**Door:** Claude

---

## Wat recent gedaan

### Mollie Betalingen Integratie

- ✅ **Database migration** - Velden voor betalingen in toernooien en judokas tabellen
- ✅ **MollieService** - Hybride service (Connect + Platform mode)
- ✅ **Toernooi model** - Helper methods (`usesMollieConnect()`, `calculatePaymentAmount()`, etc.)
- ✅ **Config** - Mollie configuratie in `config/services.php`
- ✅ **Simulatie mode** - Voor lokaal testen zonder echte Mollie keys
- ✅ **Documentatie** - `laravel/docs/2-FEATURES/BETALINGEN.md`

### Twee Betalingsmodi

| Modus | Geld naar | Toeslag |
|-------|-----------|---------|
| **Connect** | Organisator's eigen Mollie (OAuth) | Geen |
| **Platform** | JudoToernooi's Mollie | €0,50 |

### TODO Volgende Sessie

- [ ] MollieController (OAuth + webhook)
- [ ] BetalingController (start + return)
- [ ] Routes registreren + CSRF uitsluiting webhook
- [ ] Views: instellingen sectie, coach afrekenen, return pagina
- [ ] Testen met Cees' Mollie account (woensdag gepland)

### Relevante Bestanden

| Bestand | Doel |
|---------|------|
| `app/Services/MollieService.php` | API calls, OAuth, token management |
| `app/Models/Betaling.php` | Payment records |
| `app/Models/Toernooi.php` | Mollie helper methods |
| `laravel/docs/2-FEATURES/BETALINGEN.md` | Volledige documentatie |

---

## Vorige sessie (2024-12-31)

### B-Groep Slot Nummering Fix

- ✅ Visuele slot nummers gefixed (1-16 doorlopend)
- ✅ Spiegeling alleen grafisch (WIT boven, BLAUW onder)
- ✅ Debug Slots toggle in A-groep én B-groep

---

## Vorige sessie (2024-12-29 avond)

### Eliminatie System Fixes & Features

- ✅ **Winnaar doorschuiven fix** - 1/4f → 1/2f blokkade opgelost (skip already-won matches)
- ✅ **Medaille plaatsing** - Drag-drop naar goud/zilver/brons vakken
- ✅ **Swap box verbergen** - Verdwijnt na eerste wedstrijd (seeding fase voorbij)
- ✅ **Aantal bronzen instelling** - Keuze 1 of 2 bronzen medailles
- ✅ **Oogjes wachtwoord velden** - Toggle visibility voor admin/weging/etc wachtwoorden
- ✅ **Jury link 403 fix** - Missing poulesPerKlasse in RoleToegang
- ✅ **B-groep mixing** - B-winnaars naar WIT, A-verliezers naar BLAUW
- ⏪ **B-groep layout teruggedraaid** - Medailles blijven rechts (was fout geïmplementeerd)

---

## Vorige sessie (2024-12-28 middag)

### Twee KO Systemen Geïmplementeerd

**Probleem:** Gebruiker wil keuze tussen twee eliminatie systemen.

**Oplossing:** Nieuwe instelling toegevoegd met radiobuttons.

#### 1. Database Migration
- Nieuw veld `eliminatie_type` in `toernooien` tabel
- Waarden: `dubbel` (default) of `ijf`

#### 2. UI op Instellingen pagina
Locatie: Instellingen → Poule Instellingen → "Knock-out Systeem"
```
┌─────────────────────────────────────────────────────────────┐
│ Knock-out Systeem                                           │
│                                                             │
│ ○ Dubbel Eliminatie          ○ IJF Repechage               │
│   Alle verliezers krijgen       Officieel systeem: alleen   │
│   herkansing in B-groep.        verliezers van 1/4 finale   │
│   Aanbevolen voor jeugd         krijgen herkansing.         │
└─────────────────────────────────────────────────────────────┘
```

#### 3. EliminatieService volledig herschreven

**Dubbel Eliminatie (type='dubbel'):**
- Alle verliezers krijgen herkansing in B-groep
- B-groep heeft dubbele rondes: (1) = B onderling, (2) = + nieuwe A verliezers
- Formule: totaal = 2N - 5 wedstrijden
- Aanbevolen voor jeugdtoernooien (iedereen minimaal 2x judoën)

**IJF Repechage (type='ijf'):**
- Alleen verliezers van 1/4 finale krijgen herkansing
- 2 repechage pools + 2 brons wedstrijden = 4 B-wedstrijden
- Formule: totaal = N + 3 wedstrijden
- Officieel systeem voor grote toernooien

#### 4. Controllers Geüpdatet
- `BlokController` - geeft eliminatie_type door bij bracket generatie
- `MatController` - gebruikt eliminatie_type bij uitslag verwerking
- `PouleController` - gebruikt eliminatie_type bij bracket generatie

---

## Structuur Dubbel Eliminatie (24 judoka's)

```
A-groep:                              B-groep:
─────────                             ─────────
A-1/16 (8 wed) → 8 verl ──────────┐
A-1/8  (8 wed) → 8 verl ──────────┼─► B-1/8 (8 wed) → 8 win
A-1/4  (4 wed) → 4 verl ──────────│        ↓
                                  │   B-1/4(1) (4 wed) → 4 win
                                  │        ↓
                                  └─► B-1/4(2) (4 wed) → 4 win
A-1/2  (2 wed) → 2 verl ──────────────────↓
                                      B-1/2(1) (2 wed) → 2 win
                                          ↓
                                  ─► B-BRONS (2 wed) → 2x 🥉
```

**Mapping A-verliezers naar B-ronde:**
| A-ronde | Gaan naar B-ronde |
|---------|-------------------|
| A-1/16, A-1/8 | B-start of B-1/8 (eerste B-ronde) |
| A-1/4 | B-1/4(2) |
| A-1/2 | B-BRONS |

---

## Structuur IJF Repechage (24 judoka's)

```
A-groep:                              B-groep (slechts 4 wedstrijden!):
─────────                             ─────────────────────────────────
A-1/16 (8 wed)
A-1/8  (8 wed)
A-1/4  (4 wed) → 4 verl ──────────┬─► b_repechage_1 (1 wed) ─┐
                                  │   b_repechage_2 (1 wed) ─┼─► b_brons (2 wed)
A-1/2  (2 wed) → 2 verl ──────────┴─────────────────────────┘
A-finale

Totaal: N+3 = 27 wedstrijden (vs 2N-5 = 43 bij dubbel)
```

---

## Wat werkt

1. ✅ Keuze KO type op instellingen pagina
2. ✅ Bracket generatie A-groep (zelfde voor beide systemen)
3. ✅ B-groep dubbel eliminatie (alle verliezers herkansen)
4. ✅ B-groep IJF repechage (alleen 1/4 verliezers herkansen)
5. ✅ Drag-drop validatie
6. ✅ Verliezers automatisch naar juiste B-ronde

---

## Te testen

1. **Test Dubbel Eliminatie:**
   - Ga naar Instellingen → selecteer "Dubbel Eliminatie"
   - Activeer eliminatie-categorie
   - Controleer: alle verliezers gaan naar B-groep

2. **Test IJF Repechage:**
   - Ga naar Instellingen → selecteer "IJF Repechage"
   - Activeer eliminatie-categorie
   - Controleer: alleen B heeft 4 wedstrijden (2 repechage + 2 brons)
   - Controleer: alleen 1/4 en 1/2 verliezers krijgen herkansing

---

## Relevante bestanden

| Bestand | Doel |
|---------|------|
| `app/Services/EliminatieService.php` | Bracket generatie beide systemen |
| `app/Http/Controllers/MatController.php` | Drag-drop + uitslag verwerking |
| `app/Http/Controllers/BlokController.php` | Categorie activeren |
| `resources/views/pages/toernooi/edit.blade.php` | KO type keuze UI |
| `database/migrations/*_add_eliminatie_type_*.php` | Database veld |

---

## Formules

```
N = aantal judoka's
D = grootste macht van 2 ≤ N

A-groep (beide systemen):
- Wedstrijden = N - 1

B-groep DUBBEL:
- Wedstrijden = N - 4
- Totaal = 2N - 5

B-groep IJF:
- Wedstrijden = 4 (fixed)
- Totaal = N + 3
```

---

## Context

- Laravel server: http://127.0.0.1:8001
- Database: SQLite lokaal
- 4 medailles: 1x goud, 1x zilver, 2x brons

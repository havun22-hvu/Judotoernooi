# Handover - JudoToernooi

> Dit bestand wordt bijgewerkt aan het einde van elke sessie.
> Lees dit EERST bij een nieuwe sessie.

## Laatste sessie
**Datum:** 2024-12-20
**Door:** Claude

---

## Huidige status: Eliminatie Systeem

### Wat werkt
- [x] EliminatieService: bracket generatie (A + B)
- [x] Voorronde berekening: `doel = 2^n`, `voorronde = aantal - doel`
- [x] Fairness regel: bye-judokas naar b_ronde_1, voorronde-spelers naar b_ronde_2
- [x] Winnaar doorschuiven (A en B)
- [x] Mat interface toont bracket met drag & drop
- [x] Database velden: groep, ronde, bracket_positie, volgende_wedstrijd_id

### Nog te doen
- [ ] **Mat interface testen** met K.O. poules (blok 5, mat 1 + mat 2)
- [ ] Bracket weergave verbeteren (lijnen tussen rondes)
- [ ] Batch-indeling verliezers bij complete rondes testen
- [ ] Bronswedstrijden flow testen
- [ ] Uitslag registreren in bracket testen

---

## Voorronde Logica (ter referentie)

```
Voorbeeld: 23 judoka's
├── doel = 16 (grootste 2^n <= 23)
├── voorronde = 23 - 16 = 7 wedstrijden
├── voorronde_judokas = 7 × 2 = 14
├── bye_judokas = 16 - 7 = 9 (direct naar 1/8)
└── A-wedstrijden: 7 + 8 + 4 + 2 + 1 = 22
```

| Judokas | Doel | Voorronde | Bye |
|---------|------|-----------|-----|
| 5       | 4    | 1         | 3   |
| 10      | 8    | 2         | 6   |
| 15      | 8    | 7         | 1   |
| 20      | 16   | 4         | 12  |
| 23      | 16   | 7         | 9   |

---

## Context vandaag

- 2 categorieën met K.O. in **blok 5** op **mat 1** en **mat 2**
- Laravel server: http://127.0.0.1:8001
- Database: SQLite lokaal, MySQL op server

---

## Relevante bestanden

| Bestand | Doel |
|---------|------|
| `app/Services/EliminatieService.php` | Bracket generatie + uitslag verwerking |
| `resources/views/pages/mat/interface.blade.php` | Mat interface (incl. eliminatie) |
| `resources/views/pages/poule/eliminatie.blade.php` | Bracket preview pagina |
| `docs/2-FEATURES/ELIMINATIE_SYSTEEM.md` | Volledige documentatie |

---

## Notities vorige sessie

(Vul hier aan het einde van de sessie in wat belangrijk is voor morgen)

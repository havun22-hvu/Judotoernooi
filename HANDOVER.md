# Handover - JudoToernooi

> Dit bestand wordt bijgewerkt aan het einde van elke sessie.
> Lees dit EERST bij een nieuwe sessie.

## Laatste sessie
**Datum:** 2024-12-28 (ochtend volgende dag)
**Door:** Claude

---

## ⚠️ BELANGRIJKE LES
**Bij visuele/layout problemen: VRAAG EERST OM EEN SCREENSHOT OF TEKENING!**
Niet raden, niet aannames maken. Eén plaatje zegt meer dan 1000 woorden.

---

## ✅ DRAG-DROP VALIDATIE - VERBETERD

**Probleem was:** Winnaar kon naar verkeerde vak gesleept worden.

**Oorzaak gevonden:** Validatie checkte niet of judoka de WINNAAR was!

**Fix toegepast:**
1. **Nieuwe drag data velden:**
   - `isWinnaar` - boolean of deze judoka de winnaar is
   - `isGespeeld` - boolean of de wedstrijd gespeeld is

2. **Client-side validatie versterkt (`dropJudoka()`):**
   - Check: wedstrijd moet gespeeld zijn
   - Check: judoka moet de winnaar zijn
   - Check: juiste volgende wedstrijd
   - Check: juiste positie (wit/blauw)

3. **Server-side validatie versterkt (`MatController@plaatsJudoka`):**
   - Zelfde checks als client-side
   - Dubbele beveiliging

**Test scenario:**
1. Start server: `php artisan serve --port=8001`
2. Ga naar mat interface
3. Selecteer eliminatie-categorie
4. Speel een wedstrijd (markeer winnaar)
5. Sleep winnaar naar volgende ronde
6. Probeer naar VERKEERD vak → moet GEBLOKKEERD worden met alert
7. Console toont debugging info

**Debugging actief:** Console.log statements in `dropJudoka()` tonen alle drag data.

---

## Wat vandaag gedaan

### 1. EliminatieService volledig herschreven
Oude versie had bugs:
- `getRondeNaam(16)` gaf `achtste_finale` ipv `zestiende_finale`
- Voor 29 judoka's werd maar 1 wedstrijd in 1/16 gemaakt (moet 13 zijn)

**Nieuwe logica:**
```php
// Eerste ronde naam op basis van N (niet D!)
if ($n > 16) return 'zestiende_finale';  // 1/16
if ($n > 8) return 'achtste_finale';     // 1/8
// etc.
```

**Voor 29 judoka's (nu correct):**
- 1/16: 13 wedstrijden (26 vechten) + 3 byes = 16 door
- 1/8: 8 wedstrijden
- 1/4: 4 wedstrijden
- 1/2: 2 wedstrijden
- Finale: 1 wedstrijd
- **Totaal A: 28 = N-1 ✓**

### 2. Ontbrekende methodes toegevoegd
- `verwerkUitslag()` - verwerkt winnaar/verliezer (winnaar wordt NIET meer dubbel geplaatst)
- `verwijderUitB()` - verwijdert judoka uit B-groep wedstrijden

### 3. BlokController fix
- `genereerBracket()` kreeg maar 1 argument, moet 2 (`$poule`, `$judokaIds`)

### 4. Mat interface container hoogte fix
- A-groep bracket was te klein
- Nu: hoogte berekend op basis van `tweedeRonde.wedstrijden.length * 2`

### 5. Voorronde terminologie verwijderd
- `b_voorronde` → `b_start`
- Alle code en docs geüpdatet
- Legacy functies verwijderd (`renderHerkansingSimple`, `renderBronsWedstrijden`)

---

## Wat WEL werkt

1. ✅ Bracket generatie voor A-groep (correcte aantallen)
2. ✅ Bracket generatie voor B-groep
3. ✅ Winnaar krijgt groene stip
4. ✅ Verliezer gaat naar B-groep
5. ✅ Prullenbak werkt (verwijderen judoka)
6. ✅ Layout A-groep en B-groep
7. ✅ Drag-drop validatie (alleen winnaar, juiste vak, juiste positie)

---

## Te testen

1. Activeer eliminatie-categorie op mat
2. Speel een wedstrijd (markeer winnaar met groene stip)
3. Sleep winnaar naar volgende ronde
4. **TEST:** Probeer naar VERKEERD vak te slepen → alert "Verkeerde positie!"
5. **TEST:** Probeer VERLIEZER te slepen → alert "Dit is niet de winnaar!"
6. **TEST:** Sleep naar JUIST vak → moet werken

---

## Relevante bestanden

| Bestand | Doel |
|---------|------|
| `app/Services/EliminatieService.php` | Bracket generatie + uitslag verwerking |
| `app/Http/Controllers/MatController.php` | Drag-drop + validatie |
| `resources/views/pages/mat/interface.blade.php` | Frontend bracket + drag-drop |
| `app/Http/Controllers/BlokController.php` | Categorie activeren |

---

## Formules

```
N = aantal judoka's
D = grootste macht van 2 ≤ N

A-groep:
- Wedstrijden = N - 1
- Eerste ronde wedstrijden = N - D
- Byes = D - (N - D) = 2D - N

B-groep:
- Wedstrijden = N - 4

Totaal = 2N - 5
```

---

## Context

- Laravel server: http://127.0.0.1:8001
- Database: SQLite lokaal
- 4 medailles: 1x goud, 1x zilver, 2x brons

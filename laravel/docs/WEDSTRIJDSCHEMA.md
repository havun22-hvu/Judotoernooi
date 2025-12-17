# Wedstrijdschema's per Poulegrootte

Standaard wedstrijdvolgordes voor round-robin poules.
De volgorde is geoptimaliseerd zodat judoka's voldoende rust krijgen tussen wedstrijden.

## 2 judoka's (2 wedstrijden)

Dubbele confrontatie - elke judoka speelt 2x tegen de ander.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 2   | 1     |

## 3 judoka's (6 wedstrijden)

Dubbele round-robin - iedereen speelt 2x tegen elkaar.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 1   | 3     |
| 3   | 2   | 3     |
| 4   | 2   | 1     |
| 5   | 3   | 2     |
| 6   | 3   | 1     |

## 4 judoka's (6 wedstrijden)

Enkelvoudige round-robin.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 3   | 4     |
| 3   | 2   | 3     |
| 4   | 1   | 4     |
| 5   | 2   | 4     |
| 6   | 1   | 3     |

## 5 judoka's (10 wedstrijden)

Enkelvoudige round-robin.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 3   | 4     |
| 3   | 1   | 5     |
| 4   | 2   | 3     |
| 5   | 4   | 5     |
| 6   | 1   | 3     |
| 7   | 2   | 4     |
| 8   | 3   | 5     |
| 9   | 1   | 4     |
| 10  | 2   | 5     |

## 6 judoka's (15 wedstrijden)

Enkelvoudige round-robin.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 3   | 4     |
| 3   | 5   | 6     |
| 4   | 1   | 3     |
| 5   | 2   | 5     |
| 6   | 4   | 6     |
| 7   | 3   | 5     |
| 8   | 2   | 4     |
| 9   | 1   | 6     |
| 10  | 2   | 3     |
| 11  | 4   | 5     |
| 12  | 3   | 6     |
| 13  | 1   | 4     |
| 14  | 2   | 6     |
| 15  | 1   | 5     |

## 7 judoka's (21 wedstrijden)

Enkelvoudige round-robin.

| Wed | Wit | Blauw |
|-----|-----|-------|
| 1   | 1   | 2     |
| 2   | 3   | 4     |
| 3   | 5   | 6     |
| 4   | 1   | 7     |
| 5   | 2   | 3     |
| 6   | 4   | 5     |
| 7   | 6   | 7     |
| 8   | 1   | 3     |
| 9   | 2   | 4     |
| 10  | 5   | 7     |
| 11  | 3   | 6     |
| 12  | 1   | 4     |
| 13  | 2   | 5     |
| 14  | 3   | 7     |
| 15  | 4   | 6     |
| 16  | 1   | 5     |
| 17  | 2   | 6     |
| 18  | 4   | 7     |
| 19  | 1   | 6     |
| 20  | 3   | 5     |
| 21  | 2   | 7     |

---

## Formule aantal wedstrijden

- **Enkelvoudige round-robin:** n × (n-1) / 2
- **Dubbele round-robin:** n × (n-1)

| Judoka's | Enkel | Dubbel |
|----------|-------|--------|
| 2        | 1     | 2      |
| 3        | 3     | 6      |
| 4        | 6     | 12     |
| 5        | 10    | 20     |
| 6        | 15    | 30     |
| 7        | 21    | 42     |

## Aanpassen door organisator

De organisator kan via Instellingen de wedstrijdvolgorde per poulegrootte aanpassen.
Dit is handig wanneer:
- Er specifieke rustmomenten nodig zijn
- De mat-indeling bepaalde volgordes vereist
- Lokale reglementen een andere volgorde voorschrijven

---

## Mat Interface - Huidige en Volgende Wedstrijd

De mat interface toont per poule welke wedstrijd "aan de beurt" is (groen) en welke "volgende" is (geel).

### Visuele weergave

| Kleur | Betekenis | Database |
|-------|-----------|----------|
| **Groen** | Nu aan de beurt | Automatisch bepaald |
| **Geel** | Volgende wedstrijd | Handmatig of automatisch |
| **Grijs** | Gespeeld | `wedstrijden.status = 'gespeeld'` |
| **Wit** | Nog niet aan de beurt | - |

### Logica

**Groen (huidige wedstrijd)** - altijd automatisch:
- Eerste niet-gespeelde wedstrijd na de laatst gespeelde
- Beweegt alleen wanneer een wedstrijd wordt gescoord
- Kan NIET handmatig worden verplaatst

**Geel (volgende wedstrijd)**:
- Standaard: tweede niet-gespeelde wedstrijd na laatst gespeelde
- Kan handmatig worden overschreven via `poules.huidige_wedstrijd_id`
- Klik op geel (als handmatig) om terug te gaan naar automatisch
- Klik op andere niet-gespeelde wedstrijd om die als volgende te selecteren

### Database

```
poules.huidige_wedstrijd_id (nullable foreignId)
```
- `NULL` = automatische modus (standaard)
- `wedstrijd_id` = handmatige override voor volgende (gele) wedstrijd

### Bestanden

- `resources/views/pages/mat/interface.blade.php` - Mat interface (Alpine.js)
- `app/Http/Controllers/MatController.php` - API endpoint `setHuidigeWedstrijd()`
- `app/Http/Controllers/PubliekController.php` - Favorieten pagina gebruikt dezelfde logica

### Favorieten pagina

De publieke favorieten pagina toont per favoriet:
- **Groene stip** op tab = judoka is nu aan de beurt
- **Gele stip** op tab = judoka is bijna aan de beurt
- **Alert "NU!"** (groen) = favoriet is aan het vechten
- **Alert "Maak je klaar!"** (geel) = favoriet is bijna aan de beurt

De status wordt elke 15 seconden automatisch ververst.

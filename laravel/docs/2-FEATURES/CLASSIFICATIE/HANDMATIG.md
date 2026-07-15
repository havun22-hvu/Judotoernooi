---
title: Handmatig Poule Aanmaken
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Handmatig Poule Aanmaken

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Handmatig Poule Aanmaken

### Per Categorie Knoppen

Op de Poules pagina staat een **"+ Nieuwe poule"** knop per categorie header:

```
┌─────────────────────────────────────────────────────────────────┐
│ 🔵 Mini's (U7)                              [+ Nieuwe poule]    │
├─────────────────────────────────────────────────────────────────┤
│ Poule #1 Mini's -24kg (5)                                       │
│ Poule #2 Mini's -27kg (4)                                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ 🔵 Jeugd (U11)                              [+ Nieuwe poule]    │
├─────────────────────────────────────────────────────────────────┤
│ Poule #3 Jeugd 8-9j 28-32kg (5)                                 │
│ Poule #4 Jeugd 9-10j 32-35kg (4)                                │
└─────────────────────────────────────────────────────────────────┘
```

### Gedrag Gebaseerd op Categorie Type

| Categorie Type | Gewichtsklasse Veld | Uitleg |
|----------------|---------------------|--------|
| **Vaste gewichtsklassen** | Zichtbaar + verplicht | Dropdown met geconfigureerde klassen |
| **Variabele gewichten** | Verborgen | Gewichtsklasse niet nodig (range wordt berekend) |

**Detectie:** `max_kg_verschil = 0` → vaste klassen, `max_kg_verschil > 0` → variabel

### Modal Flow

1. Klik op "+ Nieuwe poule" bij categorie header
2. Modal opent met:
   - **Leeftijdsklasse**: voorgeselecteerd op aangeklikte categorie
   - **Gewichtsklasse**: alleen zichtbaar als categorie vaste gewichten heeft
3. Bij vaste gewichten: kies gewichtsklasse uit dropdown
4. Opslaan → nieuwe poule wordt aangemaakt

### Code Locaties

| Bestand | Functie |
|---------|---------|
| `resources/views/pages/poule/index.blade.php` | Per-categorie knop + modal JS |
| `app/Http/Controllers/PouleController.php` | `store()` - gewichtsklasse nullable |
| `app/Models/Toernooi.php` | `getCategorieKeyByLabel()` - lookup categorie config |

### Validatie

```php
// PouleController::store()
$validated = $request->validate([
    'leeftijdsklasse' => 'required|string',
    'gewichtsklasse' => 'nullable|string',  // Nullable voor variabele categorieën
]);
```

**Titel generatie:**
- Met gewichtsklasse: `"Mini's -24kg"`
- Zonder gewichtsklasse: `"Mini's"` (range wordt later berekend op basis van judoka's)

### Implementatie Stappen

1. **Detectie problematische poules (dynamisch)** ✅
   - Na `sluitWeging()`: check alle poules in blok
   - Bereken range op basis van gewogen gewichten
   - Markeer poules waar range > max_kg_verschil

2. **Markering afwijkende judoka's (vast)** 🚧 TODO
   - Na weging: check of judoka binnen gewichtsklasse van poule past
   - Markeer judoka in poule (rode stip/badge)
   - Judoka blijft in poule (NIET automatisch verwijderen)
   - Wachtruimte VERWIJDERD (obsoleet)

3. **UI aanpassing Wedstrijddag Poules** 🚧 TODO
   - Vaste gewichtsklassen: elke gewichtsklasse op aparte rij
   - Wachtruimte UI verwijderen
   - Afwijkende judoka's visueel gemarkeerd in poule

4. **Zoek Match** ✅
   - 🔍 knop op alle judoka's (vast + dynamisch)
   - Blok-filtering bij wedstrijddag variant
   - Groepeer resultaten per blok

5. **Data updates na verplaatsen** ✅
   - **Weegkaarten:** Dynamisch, blok/mat info update automatisch
   - **Publieke pagina's:** Deelnemer zoeken, poule overzichten, etc. tonen actuele data
   - **QR-code:** Blijft zelfde (gebaseerd op judoka ID, niet poule)
   - Alle views lezen live uit database → geen cache invalidatie nodig

---

## Legacy

De `App\Enums\Leeftijdsklasse` enum is **deprecated**:
- Bevat hardcoded JBN2025 categorieën
- Wordt niet meer gebruikt
- Nieuwe code moet preset config gebruiken

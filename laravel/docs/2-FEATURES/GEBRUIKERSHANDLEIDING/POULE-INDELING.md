---
title: Poule Indeling
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Poule Indeling

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Poule Indeling

### Gewichtsklassen Instellingen

Bij **Toernooi Bewerken** > **Gewichtsklassen** kun je kiezen:

**Met vaste gewichtsklassen:**
- Gebruik JBN 2025 of JBN 2026 preset
- Of eigen gewichtsklassen per categorie
- Geslacht instelbaar per categorie (Gemengd/M/V)

**Zonder vaste gewichtsklassen (dynamisch):**
- Drag & drop sorteer prioriteiten: Leeftijd, Gewicht, Band
- Klik (i) icoon voor uitleg over sorteer volgorde
- Stel max kg verschil in per categorie
- Stel max leeftijd verschil in per categorie

**Presets opslaan:** Sla je configuratie op als eigen preset voor later gebruik.

**Sortering bij laden eigen preset:** Categorieën worden automatisch gesorteerd:
1. Leeftijd (jong → oud)
2. Gewicht (licht → zwaar)
3. Band (laag → hoog)

Resultaat: bovenaan de lichtste mini's met witte band, onderaan de zwaarste senioren.

### Hoe Werkt de Poule Indeling?

Het systeem verdeelt judoka's in 4 stappen:

**Stap 1: CATEGORISEREN (welke groep?)**
- Per judoka wordt gekeken welke categorie past
- Categorieen worden doorlopen van jong → oud
- Eerste match waar judoka aan ALLE criteria voldoet = zijn categorie
- **Harde criteria:** max_leeftijd, geslacht, band_filter, gewichtsklasse

**Stap 2: GROEPEREN**
- Alle judoka's in dezelfde categorie = 1 groep
- Dit zijn de kandidaten voor poules binnen deze categorie

**Stap 3: SORTEREN (binnen de groep)**
- Pas NADAT judoka in categorie is geplaatst
- Sorteer volgens de ingestelde prioriteit (leeftijd/gewicht/band)
- Bepaalt alleen de volgorde, niet de groepsindeling

**Stap 4: POULES MAKEN**
- Gesorteerde groep verdelen in poules
- Voorkeur: [5, 4, 6, 3] (standaard)
- Voorbeeld: 20 judoka's → 4 poules van 5

**Belangrijk onderscheid:**
- **Categoriseren** = welke groep (HARD, alle criteria moeten matchen)
- **Sorteren** = welke volgorde binnen de groep (ZACHT, alleen volgorde)

De harde limieten (leeftijd/gewicht/geslacht/band) worden NOOIT overschreden.

### Automatische Poule Generatie

1. Ga naar **Toernooi** > **Poules** > **Genereer Poule-indeling**
2. Het systeem verdeelt judoka's automatisch op basis van:
   - Leeftijdsgroep (max 2 jaar verschil - veiligheid)
   - Gewicht (vaste klassen of max kg verschil)
   - Geslacht (per categorie instelbaar)
   - Band/niveau (sortering binnen groep)

### Poule Titels

Poule titels worden **dynamisch** samengesteld. Het formaat is:

```
#nummer Label / leeftijd / gewicht
```

**Componenten:**

| Component | Wanneer getoond | Voorbeeld |
|-----------|-----------------|-----------|
| **#nummer** | Altijd | `#1`, `#5` |
| **Label** | Als "Toon label in titel" aangevinkt | `Mini's`, `Jeugd` |
| **Leeftijd** | Als `max_leeftijd_verschil > 0` | `4j`, `9-10j` |
| **Gewicht** | Vaste klassen OF variabel (`max_kg_verschil > 0`) | `-26kg`, `28-32kg` |

**Voorbeelden:**

| Poule titel | Uitleg |
|-------------|--------|
| `#1 Mini's / 4j / -26kg` | Label aan, leeftijd variabel, vaste gewichtsklasse |
| `#2 Mini's / -26kg` | Label aan, `max_lft_verschil=0` (geen leeftijd), vaste klasse |
| `#3 Jeugd / 9-10j / 28-32kg` | Label aan, beide variabel (ranges berekend) |
| `#4 9-10j / 28-32kg` | Label uit, beide variabel |
| `#5 Mini's` | Label aan, `max_lft_verschil=0`, geen gewichtsklassen |

**Hoe werkt het?**

1. **Label**: De categorie naam (bijv. "Mini's") wordt getoond als je bij Instellingen → Categorieën het vakje "Toon label in titel" aanvinkt
2. **Leeftijd**: Wordt alleen getoond als `max_leeftijd_verschil > 0`. Bij `0` wordt aangenomen dat de leeftijd al in het label zit (bijv. "U9 Jongens")
3. **Gewicht**:
   - Bij **vaste gewichtsklassen** (bijv. -20, -23, -26): toont de klasse naam
   - Bij **variabel gewicht** (`max_kg_verschil > 0`): toont de berekende range uit de judoka's in de poule
   - Bij geen gewichtsklassen EN `max_kg_verschil = 0`: geen gewicht getoond

**Tip:** Wijzig de categorie naam in Instellingen VOORDAT je poules genereert.

### Poule Regels

- **Optimaal**: 5 judoka's per poule (10 wedstrijden)
- **Minimum**: 3 judoka's (6 wedstrijden - dubbele ronde)
- **Maximum**: 6 judoka's (15 wedstrijden)

### Handmatige Aanpassingen

**Drag & drop**: Sleep judoka's direct tussen poules op de Poules pagina.

**Bij verplaatsen worden automatisch bijgewerkt:**
- Aantal judoka's en wedstrijden per poule
- Totaal statistieken bovenaan de pagina (wedstrijden, judoka's, problemen)
- Min-max leeftijd per poule
- Min-max gewicht per poule
- Poule titel (bij variabele categorie)


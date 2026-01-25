# JudoToernooi Features

## Classificatie

> **Volledige docs:** `laravel/docs/2-FEATURES/CLASSIFICATIE.md`

**Presets:**
| Type | Opslag |
|------|--------|
| JBN 2025/2026 | Hardcoded PHP |
| Eigen presets | Database |

**Harde criteria (NOOIT overschreden):**
- Categorie: max_leeftijd, geslacht, band_filter
- Poule: max_kg_verschil, max_leeftijd_verschil

**Indeling Modi:**
| Modus | Wanneer |
|-------|---------|
| **Vaste klassen** | max_kg_verschil = 0 |
| **Dynamisch** | max_kg_verschil > 0 |

## Authenticatie

> **Docs:** `laravel/docs/4-PLANNING/PLANNING_AUTHENTICATIE_SYSTEEM.md`

| Rol | Authenticatie |
|-----|---------------|
| **Organisator** | Email + wachtwoord |
| **Beheerders** | Email + wachtwoord |
| **Mat/Weging/etc** | URL + PIN + device binding |
| **Coachkaart** | Device binding + foto |

**Device Binding:**
1. Organisator maakt toegang aan (URL + PIN)
2. Vrijwilliger opent URL, voert PIN in
3. Device wordt gebonden
4. Daarna: direct toegang

## Categorieën: Vast vs Variabel

**VASTE** (`max_kg_verschil = 0`):
- ✅ Poules, Kruisfinales, Eliminatie

**VARIABELE** (`max_kg_verschil > 0`):
- ✅ Alleen poules
- ❌ Geen kruisfinales/eliminatie

## UI Conventies

| Element | Notatie |
|---------|---------|
| Poule | `#1`, `#42` |
| Wedstrijd | `W1`, `W2` |

## Portaal Modus

| Modus | Nieuw | Wijzigen | Verwijderen |
|-------|-------|----------|-------------|
| **uit** | ❌ | ❌ | ❌ |
| **mutaties** | ❌ | ✅ | ❌ |
| **volledig** | ✅ | ✅ | ✅ |

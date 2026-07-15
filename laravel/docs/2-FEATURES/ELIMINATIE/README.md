---
title: Eliminatie Systeem (Double Elimination)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Eliminatie Systeem (Double Elimination)

> **Status**: Actief
> **Laatste update**: 2026-02-08
> **Verantwoordelijke**: EliminatieService.php

## Overzicht

Het double elimination systeem zorgt ervoor dat judoka's pas na **twee nederlagen** uitgeschakeld zijn.

```
A-groep (Hoofdboom)  →  Goud + Zilver
B-groep (Herkansing) →  Brons (1 of 2, instelbaar)

Verlies in A = naar B-groep
Verlies in B = uitgeschakeld
```

> Dit is het index-doc van de eliminatie-documentatie: overzicht en quick reference
> hieronder, de details in de deeldocs onder *Waar staat wat*.

## Quick Reference

### Minimum Grootte

**Eliminatie poules vereisen minimaal 8 judoka's.**

Bij verificatie ("Verifieer poules" knop) worden eliminatie poules apart behandeld:
- Normaal: 3-6 judoka's
- **Eliminatie: min. 8 judoka's** (geen maximum)
- Kruisfinale: geen grootte validatie

Zie ook: [CLASSIFICATIE.md](../CLASSIFICATIE.md)

### Kernformules

```
N  = aantal judoka's
D  = 2^floor(log2(N))           # grootste macht van 2 <= N
a1 = verliezers eerste A-ronde  # N-D (of D/2 bij exacte macht van 2)
a2 = verliezers tweede A-ronde  # D/2 (of D/4 bij exacte macht van 2)

SAMEN  als: a1 <= a2  (a1 past in B-start, evt. met byes op WIT)
DUBBEL als: a1 > a2   (a1 te groot, extra (1)-ronde nodig)
```

### Wedstrijden Totaal

| Instelling | A-groep | B-groep | Totaal |
|------------|---------|---------|--------|
| 2 brons (default) | N - 1 | N - 4 | 2N - 5 |
| 1 brons | N - 1 | N - 3 | 2N - 4 |

### B-Structuur Bepalen

| Conditie | B-structuur | Voorbeeld |
|----------|-------------|-----------|
| a1 ≤ a2 | **SAMEN** (geen suffix) | N=5-6, 9-12, 17-24, 33-48 |
| a1 > a2 | **DUBBEL** met (1)/(2) | N=7-8, 13-16, 25-32, 49-64 |

Zie [FORMULES.md](./FORMULES.md) voor de volledige berekening incl. exacte machten van 2.

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [FORMULES.md](./FORMULES.md) | Je wilt weten hoe D, V1, a1 en a2 berekend worden en of een N SAMEN of DUBBEL is |
| [SLOT-SYSTEEM.md](./SLOT-SYSTEEM.md) | Je zoekt welk slotnummer bij welk potje hoort, of hoe een judoka doorschuift |
| [TEST-MATRIX.md](./TEST-MATRIX.md) | Je wilt per N controleren of het aantal wedstrijden, byes en rondes klopt |
| [VEREISTEN.md](./VEREISTEN.md) | Eliminatie is disabled in de instellingen, of je vraagt je af waarom Δkg/Δlft 0 moet zijn |
| [B-START-BYES.md](./B-START-BYES.md) | Een B(1)-wedstrijd heeft maar één judoka en je wilt weten of dat klopt |
| [IMPLEMENTATIE.md](./IMPLEMENTATIE.md) | Je raakt `EliminatieService`: bracket genereren, uitslag verwerken, doorschuiven, correcties, DB-velden |
| [B-MAT-EN-WEERGAVE.md](./B-MAT-EN-WEERGAVE.md) | B-groep moet op een andere mat, of een poule staat niet op de pagina waar je hem verwacht |
| [BRACKET-OPBOUW.md](./BRACKET-OPBOUW.md) | Je werkt aan het tekenen van de bracket: Blade partials, data flow, drag-and-drop, posities |
| [BRACKET-RONDES.md](./BRACKET-RONDES.md) | Je voegt een ronde-naam toe, of je werkt aan beurtaanduiding, bracket-bestanden of routes |

## Gerelateerde Bestanden

- `app/Services/EliminatieService.php` - Bracket generatie, uitslag verwerking
- `app/Services/BracketLayoutService.php` - Visuele layout berekening
- `resources/views/pages/mat/partials/_content.blade.php` - Mat interface JS
- `resources/views/pages/mat/partials/_bracket*.blade.php` - Blade partials
- `database/migrations/*_eliminatie_*.php` - Schema

## Changelog

Zie [CHANGELOG.md](../../../CHANGELOG.md) voor wijzigingsgeschiedenis.

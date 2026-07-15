---
title: Poule statistieken en de twee fasen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Poule statistieken en de twee fasen

> Onderdeel van [Ontwikkelaar Gids](../ONTWIKKELAAR.md).

### Poule Statistieken Synchronisatie

De velden `aantal_judokas` en `aantal_wedstrijden` in de poules tabel zijn gecached waarden:

```php
// Model methode om te herberekenen
$poule->updateStatistieken();

// Artisan command voor controle/correctie
php artisan poules:herbereken --check  // Alleen controleren
php artisan poules:herbereken          // Corrigeren
```

**Automatische update via helper methodes:**
```php
$poule->voegJudokaToe($judoka);    // attach + updateStatistieken
$poule->verwijderJudoka($judoka);  // detach + updateStatistieken
```

### Twee Fasen: Voorbereiding vs Toernooidag

Het systeem kent twee fasen met verschillende logica:

**Fase 1: Voorbereiding (weken voor toernooi)**
- Judoka's importeren, poules maken, blokkenverdeling
- Geen aanwezigheid status - alle judoka's tellen mee
- `updateStatistieken()` telt alle judoka's in poule

**Fase 2: Toernooidag**
- Weging, overpoelen, wedstrijden
- Aanwezigheid status wordt relevant
- Judoka's worden fysiek verplaatst (attach/detach) bij:
  - Overpoelen (te zwaar → zwaardere klasse)
  - Afwezig melden (uit poule halen)

**Belangrijk:** De aanwezigheid filtering hoort bij het VERPLAATSEN van judoka's, niet bij het TELLEN. Wie in de poule zit, telt mee.

**Pages per fase:**

| Voorbereiding | Toernooidag |
|---------------|-------------|
| Judoka's importeren | Weging interface |
| Poule indeling | Wedstrijddag (overpoelen) |
| Blokkenverdeling | Zaaloverzicht |
| - | Mat interface |
| - | Spreker interface |


---
title: Gebruikershandleiding
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Gebruikershandleiding

> Complete handleiding voor het draaien van een judotoernooi, van inschrijving tot prijsuitreiking.
> Dit is een **index-doc**: hieronder staat het totaaloverzicht van beide fasen en de lockdown-regels;
> de uitwerking per stap staat in de deeldocs in [`GEBRUIKERSHANDLEIDING/`](GEBRUIKERSHANDLEIDING/).

## Overzicht

Het JudoToernooi Management Systeem ondersteunt het complete proces van een judotoernooi in twee fasen:

### Fase 1: Voorbereiding (weken/maanden voor toernooi)
1. Toernooi aanmaken
2. Inschrijving (tot sluitingsdatum):
   - **Handmatige import** - Organisator importeert CSV/Excel
   - **Coach portal** - Coaches voegen zelf judoka's toe
3. **Einde inschrijving** → "Valideer judoka's" → QR-codes aangemaakt (definitief)
4. Poules genereren
5. Blokverdeling → poules krijgen blok toegewezen
6. **"Verdeel over matten"** → automatische verdeling (organisator kan nog schuiven)
7. **Zaaloverzicht** → controleer verdeling, pas aan indien nodig
8. **Resultaat:** Weeglijst, weegkaarten (blok+mat), coachkaarten klaar (dynamisch, altijd actueel)

### Fase 2: Toernooidag
1. Weging (gewicht registreren per judoka)
2. Einde weegtijd per blok
3. **Wedstrijddag Poules** → overpoelen (te zware/afwezige judoka's)
4. Per poule: **"→"** knop klikken (knop wordt groen ✓)
5. **Zaaloverzicht** → witte chip klikken → wedstrijdschema genereren (chip wordt groen)
6. Wedstrijden op mat
7. Poule klaar → spreker voor prijsuitreiking

**Belangrijk:**
- Matten worden automatisch verdeeld, maar organisator kan altijd schuiven in Zaaloverzicht
- Weegkaarten zijn dynamisch en tonen altijd actuele blok + mat info
- Wedstrijdschema's worden PAS gegenereerd bij activatie op toernooidag (chip klikken)

### Lockdown na Start Wedstrijddag

**Zodra de weging van een blok wordt gesloten** (`weging_gesloten = true`):

| Pagina | Status | Reden |
|--------|--------|-------|
| **Poules** (voorbereiding) | LOCKED | Weegkaarten zijn uitgedeeld met mat-nummers |
| **Blokken** | LOCKED | Blokverdeling bepaalt coachkaarten |
| **Zaaloverzicht** | LOCKED | Schema's zijn geprint/zichtbaar |
| **Wedstrijddag Poules** | EDITABLE | Live aanpassingen voor overpoulers |

**Poules aangemaakt op wedstrijddag:**
- Zijn ECHTE poules (worden gewoon gespeeld)
- Verschijnen NIET op de Poules pagina (andere context)
- Worden verwijderd bij blok-reset

**Enige manier om voorbereiding te heropenen:** Reset via Instellingen > Organisatie

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [Voorbereiding](GEBRUIKERSHANDLEIDING/VOORBEREIDING.md) | Toernooi aanmaken, CSV/Excel importeren, judoka's verwijderen, judoka-codes, budoclubs beheren, inschrijfflow en de import-correctie-workflow. |
| [Coachkaarten & Valideer Judoka's](GEBRUIKERSHANDLEIDING/COACHKAARTEN-VALIDATIE.md) | Coachkaarten uitdelen aan wedstrijdcoaches, en de inschrijving definitief sluiten met "Valideer judoka's" (QR-codes aanmaken). |
| [Poule Indeling](GEBRUIKERSHANDLEIDING/POULE-INDELING.md) | Gewichtsklassen instellen, poules automatisch genereren, poule-titels en -regels, handmatig judoka's verschuiven. |
| [Blok/Mat Verdeling](GEBRUIKERSHANDLEIDING/BLOK-MAT-VERDELING.md) | Poules over blokken en matten verdelen, varianten berekenen, weegkaarten printen en de voorbereiding afsluiten. |
| [Toernooidag & Weging](GEBRUIKERSHANDLEIDING/TOERNOOIDAG-WEGING.md) | De dagflow per blok, wegen via admin of scanner-PWA, gewichtscontrole, weging sluiten en automatische aanwezigheidsbepaling. |
| [Overpoelen](GEBRUIKERSHANDLEIDING/OVERPOELEN.md) | Na de weging judoka's herindelen die te zwaar zijn of niet komen opdagen, en afmeldingen verwerken. |
| [Zaaloverzicht, Activatie & Mat Interface](GEBRUIKERSHANDLEIDING/ZAALOVERZICHT-MAT-INTERFACE.md) | Categorieën activeren (chip-kleuren), wedstrijden op de mat draaien, uitslagen registreren, blessures en poules afronden. |
| [Spreker, Prijsuitreiking & Statistieken](GEBRUIKERSHANDLEIDING/SPREKER-STATISTIEKEN.md) | Prijsuitreikingen aankondigen, eindoverzicht, en resultaten voor organisator, coach portal en de openbare website. |

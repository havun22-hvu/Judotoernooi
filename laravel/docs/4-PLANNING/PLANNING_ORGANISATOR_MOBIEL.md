# Planning: Organisator Mobiel (Smartphone PWA)

> **Status:** V1 GEBOUWD (30 maart 2026)
> **Datum:** 30 maart 2026
> **Concept:** Organisator loopt door de zaal en lost problemen op via smartphone
> **Principe:** Niet een heel toernooi organiseren op telefoon — alleen quick-fix functies onderweg

---

## Kernidee

De organisator (of hoofdjury) loopt tijdens een toernooi rond in de zaal. Ze komen problemen tegen die ze **direct** willen oplossen zonder terug te lopen naar de laptop.

Dit is GEEN vervanging van het volledige dashboard — het is een **mobiele companion** met de functies die je nodig hebt als je rondloopt.

---

## Use Cases (geprioriteerd)

### 1. Judoka Gewicht + Poule Zoeken + Overpoulen
**Scenario:** Coach komt naar organisator: "Mijn judoka zit in de verkeerde poule" of "gewicht klopt niet"

**Functies:**
- Zoekfunctie: zoek judoka op naam of club
- Toon: huidige poule, gewicht, categorie
- Gewicht invullen/corrigeren
- Poule bekijken (wie zit erin, gewichten, regels)
- Judoka verplaatsen naar andere poule (overpoulen)

**Prioriteit:** HOOG — meest voorkomend probleem

---

### 2. Judoka Toevoegen aan Poule
**Scenario:** Last-minute aanmelding, of judoka vergeten in te delen

**Functies:**
- Judoka selecteren (uit niet-ingedeelde judoka's)
- OF nieuwe judoka aanmaken (naam, club, gewicht, geboortejaar, band, geslacht)
- Poule kiezen of automatisch laten indelen
- Direct zichtbaar op mat-interface

**Prioriteit:** HOOG — komt regelmatig voor

---

### 3. Mat/Poule Voortgang Overzicht
**Scenario:** Organisator wil weten: "Hoeveel wedstrijden nog per mat? Kan ik een poule overzetten?"

**Functies:**
- Per mat: aantal resterende wedstrijden
- Per poule op die mat: aantal resterende wedstrijden
- Indicatie: welke mat loopt achter/voor
- Actie: poule verplaatsen naar andere mat (einde-blok beslissing)

**Prioriteit:** HOOG — essentieel voor planning wedstrijddag

---

### 4. Chat met Andere PWA's
**Scenario:** Organisator wil bericht sturen naar mat-vrijwilligers of weging

**Functies:**
- Chat naar alle devices (broadcast)
- Chat naar specifieke mat/weging/dojo
- Berichten ontvangen van mat-vrijwilligers
- Gebruik bestaand chat systeem (Reverb)

**Prioriteit:** MIDDEL — handig, chat systeem bestaat al deels

---

## Buiten Scope

| Feature | Reden |
|---------|-------|
| Spreker interface | Bestaat al als aparte PWA |
| Volledige poule-indeling | Te complex voor telefoon, doe je op laptop |
| Toernooi instellingen | Eenmalig, doe je vooraf op laptop |
| Financieel/betalingen | Niet relevant tijdens wedstrijddag |
| Eliminatie bracket beheer | Te complex voor klein scherm |

---

## Technische Richting (BESLOTEN)

**Beslissing:** Responsive dashboard met mobiele modus — geen aparte PWA.

### Waarom geen aparte PWA?
- Organisator is al ingelogd (email+wachtwoord) — geen extra auth nodig
- API's en data bestaan al (zoeken, poule verplaatsen, wedstrijd telling)
- Dubbel onderhoud vermijden — 1 codebase, niet 2
- Chat widget zit al in het dashboard

### Hoe het werkt
- Dashboard detecteert mobiel/desktop
- **Desktop:** Bestaand dashboard (ongewijzigd)
- **Mobiel:** Quick-action cards met touch-geoptimaliseerde UI

### Belangrijke UX: verwijzing naar volledige app
Op de mobiele view moet **duidelijk zichtbaar** zijn:
```
┌─────────────────────────────────┐
│  💻 Volledige voorbereiding?    │
│  Open de app op tablet of PC    │
│  voor alle functies.            │
└─────────────────────────────────┘
```
Dit voorkomt dat organisatoren denken dat de smartphone-view alles is.
De hele app voor toernooi voorbereiding is beschikbaar op tablet en PC.

### Mobiele UI structuur
```
┌─────────────────────────────────┐
│  Toernooinaam        [☰ Menu]   │
├─────────────────────────────────┤
│                                 │
│  ┌─────────────────────────┐    │
│  │ 🔍 Judoka Zoeken        │    │
│  │   Gewicht · Poule ·     │    │
│  │   Overpoulen            │    │
│  └─────────────────────────┘    │
│                                 │
│  ┌─────────────────────────┐    │
│  │ ➕ Judoka Toevoegen      │    │
│  │   Aan poule indelen     │    │
│  └─────────────────────────┘    │
│                                 │
│  ┌─────────────────────────┐    │
│  │ 📊 Mat Voortgang        │    │
│  │   Wedstrijden per       │    │
│  │   mat & poule           │    │
│  └─────────────────────────┘    │
│                                 │
│  ┌─────────────────────────┐    │
│  │ 💬 Chat                 │    │
│  │   Berichten naar        │    │
│  │   mat/weging/dojo       │    │
│  └─────────────────────────┘    │
│                                 │
└─────────────────────────────────┘
```

### Bestaande code hergebruiken

| Functie | Bestaande code | Hergebruik |
|---------|---------------|------------|
| Judoka zoeken | `weging/partials/_content.blade.php` → `searchJudoka()` | Zoeklogica + debounce |
| Poule bekijken | `wedstrijddag/poules` pagina | API endpoints |
| Overpoulen | `PouleController::verplaatsJudokaApi` / `WedstrijddagController::verplaatsJudoka` | API call |
| Judoka toevoegen | Voorbereiding → deelnemers | Formulier + indeling API |
| Mat voortgang | Mat interface → wedstrijd telling | Query's per mat/poule |
| Chat | `partials.chat-widget` | Direct include |

### Routes (binnen bestaande auth:organisator + admin middleware)
```php
Route::get('wedstrijddag/mobiel', [WedstrijddagController::class, 'mobiel'])->name('wedstrijddag.mobiel');
Route::get('wedstrijddag/mat-voortgang', [WedstrijddagController::class, 'matVoortgangApi'])->name('wedstrijddag.mat-voortgang');
Route::get('wedstrijddag/poules-api', [WedstrijddagController::class, 'poulesApi'])->name('wedstrijddag.poules-api');
// Hergebruikt: judoka.zoek, weging.registreer, wedstrijddag.verplaats-judoka, wedstrijddag.nieuwe-judoka
```

### Bestanden (V1 implementatie)
```
app/Http/Controllers/WedstrijddagController.php  → mobiel(), matVoortgangApi(), poulesApi()
resources/views/pages/toernooi/mobiel.blade.php   → mobiele view (Alpine.js)
resources/views/pages/toernooi/dashboard.blade.php → "Mobiel" knop in header
routes/web.php                                     → 3 nieuwe routes
```

---

## Afhankelijkheden

| Feature | Status | Nodig voor |
|---------|--------|------------|
| Zoekfunctie judoka's | Bestaat (wedstrijddag) | Use case 1 |
| Poule verplaatsen API | Bestaat | Use case 1 |
| Judoka toevoegen | Bestaat (voorbereiding) | Use case 2 |
| Wedstrijd telling per mat | Deels (mat interface) | Use case 3 |
| Chat systeem | Bestaat (Reverb) | Use case 4 |

---

## Open Vragen

1. ~~Moet dit een aparte PWA zijn?~~ → **Nee, responsive dashboard** (besloten 30-03-2026)
2. ~~Authenticatie?~~ → **Bestaande organisator login** (besloten 30-03-2026)
3. Moet hoofdjury hier ook toegang toe hebben? (zij hebben al `layouts.app` met menu)
4. Welke use case bouwen we eerst?

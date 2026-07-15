---
title: Voorbereiding
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Voorbereiding

> Onderdeel van [Gebruikershandleiding](../GEBRUIKERSHANDLEIDING.md).

## Voorbereiding

### Nieuw Toernooi Aanmaken

1. Ga naar **Dashboard** > **Nieuw Toernooi**
2. Vul de toernooi gegevens in:
   - Naam (bijv. "6e WestFries Open")
   - Datum
   - Aantal matten (standaard: 7)
   - Aantal tijdsblokken (standaard: 6)
3. Klik **Aanmaken**

### Deelnemers Importeren

**Stap 1: Bestand uploaden**
1. Ga naar **Toernooi** > **Judoka's** > **Importeren**
2. Upload een CSV of Excel bestand (ondersteund: .csv, .xlsx, .xls)

**Stap 2: Kolom toewijzing (drag-and-drop)**

Na upload verschijnt een preview scherm met twee panelen:

| Links: Database velden | Rechts: CSV kolommen uit bestand |
|------------------------|----------------------------------|
| Naam * | **Naam** → Saar Peters, Noah... |
| Club | **Club** → Sportclub X, Judo Y... |
| Geboortejaar * | **Geboortejaar** → 2015, 2016... |
| Geslacht (M/V) * | **Geslacht** → M, V, M... |
| Gewicht (kg) | **Gewicht** → 32.5, 28.0... |
| Band | **Band** → Wit, Geel... |
| Gewichtsklasse | |

**Hoe het werkt:**
- Het systeem detecteert automatisch kolommen op basis van de kolomnaam
- Elke CSV kolom toont **voorbeelddata** zodat je kunt controleren of de inhoud klopt
- **Sleep** een CSV kolom naar het juiste database veld als de automatische detectie fout is
- Klik **✕** om een koppeling te verwijderen en opnieuw te slepen
- De preview tabel onderaan toont welke kolommen gekoppeld zijn (groen gemarkeerd)

**Stap 3: Importeren**
- Controleer of alle verplichte velden (*) gekoppeld zijn
- Klik **Importeren** om de data te verwerken

**Het systeem:**
- Berekent automatisch de leeftijdsklasse (indien geboortejaar bekend)
- Bepaalt de gewichtsklasse op basis van gewicht
- Als alleen gewichtsklasse is opgegeven (bv. "-34"), wordt gewicht afgeleid (34 kg)
- **Onvolledige judoka's** (ontbrekend geboortejaar/geslacht) worden geïmporteerd en gemarkeerd
- Filter op onvolledige judoka's via de "Onvolledig" knop in de deelnemerslijst
- Import melding toont aantal geïmporteerd + aantal duplicaten bijgewerkt

**Let op:**
- QR-codes worden pas aangemaakt bij "Valideer judoka's" (na einde inschrijving)
- Weegkaarten zijn dynamisch en tonen altijd actuele blok + mat info (zodra toegewezen)

### Judoka Verwijderen

Individuele judoka's kunnen verwijderd worden uit het overzicht:

1. Ga naar **Toernooi** > **Judoka's**
2. Klik op de **×** knop rechts in de rij
3. Bevestig de verwijdering in de popup

**Let op:**
- Verwijderen is definitief en kan niet ongedaan worden gemaakt
- Verwijder geen judoka's die al in poules zijn ingedeeld (verwijder ze eerst uit de poule)

### Judoka Codes

Elke judoka krijgt een unieke code (bv. `U1234M01`) voor poule-indeling. Deze code wordt berekend op basis van:
- Leeftijdsklasse
- Gewichtsklasse
- Band
- Geslacht

**Wanneer worden codes (her)berekend?**
- Na elke **import** - automatisch
- Bij klikken op **"Valideren"** knop
- Bij wijziging **prioriteit instellingen** (drag & drop volgorde) - automatisch bij opslaan

### Budoclub vs Wedstrijdcoach

Het systeem maakt onderscheid tussen twee concepten:

| Concept | Doel | Wanneer aangemaakt |
|---------|------|-------------------|
| **Budoclub** | Inschrijven judoka's via portal | Bij toevoegen club door organisator |
| **Wedstrijdcoach** | Begeleiding judoka's op wedstrijddag | Bij aanmelden 1e judoka |

**Budoclub:**
- Krijgt portal URL + PIN voor inschrijving
- Kan judoka's aanmelden/wijzigen tot deadline
- Elke bekende club krijgt uitnodiging

**Wedstrijdcoach:**
- Fysieke begeleider op de wedstrijddag
- Krijgt coachkaart (toegang dojo)
- Minimaal 1 per club met judoka's
- Definitief aantal berekend bij "Einde Voorbereiding"

### Inschrijfflow

**Workflow voor een toernooi:**

1. **Organisator maakt toernooi aan** met inschrijfdeadline
2. **Organisator voegt budoclubs toe** (via Clubs pagina)
   - Per club wordt automatisch een portal URL + PIN aangemaakt
3. **Organisator deelt URL + PIN** met elke budoclub (email, WhatsApp, etc.)
4. **Budoclubs schrijven judoka's in** via hun portal (tot deadline)
   - Bij 1e judoka: automatisch 1 wedstrijdcoach aangemaakt
5. **Na deadline**: Organisator valideert, poules en blokken worden gemaakt
6. **Einde Voorbereiding**: Definitief aantal coachkaarten berekend per club

### Budoclubs Beheren

Ga naar **Toernooi** > **Clubs** om budoclubs te beheren:

**Club toevoegen:**
1. Vul clubnaam (+ optioneel plaats, email, telefoon, website) in
2. Klik **Toevoegen**
3. Automatisch wordt aangemaakt:
   - Portal URL (bijv. `/school/ABC123`)
   - PIN code (5 cijfers)

**URL en PIN bekijken/delen:**

Per club toont de tabel de portal toegang:
- **URL** → kopieer knop voor portal link
- **PIN** → kopieer knop voor pincode
- **WhatsApp** → groene knop opent WhatsApp met vooringevuld bericht (URL + PIN)
  - Met telefoonnummer: gaat direct naar dat contact
  - Zonder telefoonnummer: lichtgroene knop, kies zelf contact

**Uitnodiging per email:**
- **Alle Uitnodigen** - Stuurt email naar alle clubs met email adres
- **Email** knop per club - Stuurt individuele email

**Club bewerken:**
- Klik **Bewerken** (groene knop) → modal popup opent
- Pas gegevens aan en klik **Opslaan**

**Club verwijderen:**
- Klik **Delete** (rode knop)
- Als club judoka's heeft: eerst waarschuwing "X judoka's worden ook verwijderd"
- Daarna: bevestiging "Weet je zeker dat je [club] wilt verwijderen?"

**Email bevat:**
- Toernooi info (naam, datum, locatie, deadline)
- Portal URL voor de club
- PIN code om in te loggen
- Instructies voor inschrijving

### Import Correctie Workflow

Bij import van judoka's (CSV) kunnen er onvolledige gegevens zijn. Het systeem:

1. **Detecteert problemen** tijdens import (ontbrekend geslacht, onbekende club, etc.)
2. **Markeert judoka's** met `import_status = 'te_corrigeren'`
3. **Toont overzicht** aan organisator per club

**Organisator ziet:**
- Aantal te corrigeren judoka's per club
- Welke velden ontbreken per judoka
- Optie om correctie email te sturen

**Correctie email:**
- Automatisch verstuurd naar clubs met te corrigeren judoka's
- Bevat lijst van judoka's die correctie nodig hebben
- Directe link naar portal om gegevens aan te vullen

**Budoclub portal toont:**
- Waarschuwing bovenaan als er te corrigeren judoka's zijn
- Per judoka de import warnings
- Coach kan gegevens direct aanpassen en opslaan


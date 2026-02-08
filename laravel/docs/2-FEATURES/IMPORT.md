# Judoka Import

## Overzicht

Import van judoka's via CSV/Excel bestand door de organisator.

## Workflow

### Organisator workflow
1. Organisator uploadt CSV/Excel bestand
2. Systeem analyseert kolommen en toont preview met drag & drop mapping
3. Organisator past kolom-mapping aan indien nodig (incl. multi-column voor naam)
4. Organisator bevestigt import
5. Systeem importeert judoka's
6. Bij problemen: clubs krijgen melding

### Club correctie workflow
1. Import detecteert problemen (ontbrekend/ongeldig geboortejaar, gewicht, etc.)
2. Judoka krijgt `import_status = 'te_corrigeren'`
3. Club krijgt email: "X judoka's hebben correctie nodig"
4. Coach portal toont gele "Te corrigeren" sectie bovenaan
5. Coach past gegevens aan en klikt "Opslaan"
6. Systeem hervalideert → als compleet: `import_status = 'compleet'`, warning weg

## Import status velden

### judokas.import_status
| Status | Betekenis |
|--------|-----------|
| `null` | Nog niet geïmporteerd (handmatig toegevoegd) |
| `compleet` | Geïmporteerd, alle gegevens correct |
| `te_corrigeren` | Geïmporteerd, maar gegevens ontbreken/incorrect |

### Wat maakt een judoka "te_corrigeren"?
- Geboortejaar ontbreekt of ongeldig (< 1950, > huidig jaar)
- Gewicht ontbreekt of onrealistisch (< 15 kg of > 150 kg)
- Geslacht ontbreekt of niet herkend

## Foutafhandeling

### Import fouten (judoka NIET aangemaakt)
- Alleen bij fatale fouten (geen naam, dubbele entry)
- Worden getoond in oranje box na import
- Opgeslagen in `toernooien.import_fouten` (JSON veld)

### Import warnings (judoka WEL aangemaakt, maar met problemen)
- Opgeslagen in `judokas.import_warnings` veld (tekst beschrijving)
- `import_status = 'te_corrigeren'` voor actie door club
- Getoond op:
  - **Admin judoka pagina**: Rode box met warnings per club + contactgegevens
  - **Coach portal**: Gele "Te corrigeren" sectie + rode tekst bij judoka

### Wanneer ontstaan warnings?
- Geslacht niet herkend (bv. "X") → standaard M gebruikt
- Gewicht > 100 kg of < 15 kg → "lijkt hoog/laag"
- Leeftijd < 4 of > 50 jaar → "erg jong/hoog"
- Geboortejaar ontbreekt
- Gewicht ontbreekt

### Zonder club melding
- Na import wordt geteld hoeveel judoka's **geen club** hebben
- Gele waarschuwingsbanner op judoka index: "X judoka('s) zonder club geïmporteerd"
- Organisator kan per judoka de club alsnog invullen via de deelnemerslijst
- Club is **niet verplicht** — judoka's worden altijd geïmporteerd, ook zonder club

## Kolom detectie

### Automatische detectie
Het systeem herkent automatisch kolommen op basis van headers (case-insensitive):
- **naam**: naam, name, volledige naam, judoka, deelnemer, voornaam, achternaam
- **club**: club, vereniging, sportclub, judoclub, judoschool, school, dojo
- **geboortejaar**: geboortejaar, geboortedatum, jaar, geb.jaar, birthdate, dob
- **geslacht**: geslacht, gender, sex, m/v
- **gewicht**: gewicht, weight, kg
- **band**: band, gordel, belt, kyu, graad
- **telefoon**: telefoon, tel, phone, mobiel, gsm

### Multi-column velden (naam)
Als een bestand `voornaam` en `achternaam` als aparte kolommen heeft:
1. `voornaam` wordt automatisch gedetecteerd als "naam"
2. Gebruiker kan `achternaam` (en evt. `tussenvoegsel`) erbij slepen
3. Kolommen worden gecombineerd met spatie: "Jan de Vries"
4. Volgorde is aan te passen door chips te herschikken

### Geboortedatum → Geboortejaar
Formaten worden automatisch geparsed:
- `2015` → 2015
- `2015-03-15` → 2015
- `15-03-2015` → 2015

## Belangrijke regels

### Gewichtsklasse kolom in import preview
- Gewichtsklasse kolom wordt **ALLEEN** getoond als toernooi vaste gewichtsklassen heeft
- Vaste gewichtsklassen = minstens 1 categorie met gevulde `gewichten` array (bijv. `-22, -25, -30`)
- Dynamische categorieën (alleen Δkg, geen gewichten) → **GEEN** gewichtsklasse kolom
- **Sla eerst de categorieën op** voordat je importeert, anders wordt de check niet correct uitgevoerd

### Gewichtsklasse is NOOIT verplicht
- Gewicht is belangrijk, gewichtsklasse niet
- Als categorie niet geconfigureerd → `gewichtsklasse = 'Onbekend'`
- Als variabele categorie → `gewichtsklasse = 'Variabel'`
- **Import mag NOOIT falen omdat gewichtsklasse null is**

### Judoka's worden ALTIJD geïmporteerd
- Ook als categorie niet bestaat in config
- Ook als gewichtsklasse niet bepaald kan worden
- Organisator kan later categorie aanpassen of config uitbreiden

## Database velden

### judokas tabel
```
import_warnings  TEXT NULL     -- Warnings bij import (bv. "Geslacht 'X' niet herkend")
import_status    VARCHAR NULL  -- null, 'compleet', 'te_corrigeren'
```

### toernooien tabel
```
import_fouten    JSON NULL  -- Fouten van laatste import (judoka's die NIET zijn aangemaakt)
```

## Code locaties

### Import
- `app/Services/ImportService.php` - Import logica
  - `importeerDeelnemers()` - Hoofdfunctie
  - `verwerkRij()` - Verwerkt één rij, zet import_status
  - `classificeerJudoka()` - Bepaalt categorie + gewichtsklasse
- `app/Http/Controllers/JudokaController.php` - Import endpoints
  - `importForm()` - Upload formulier
  - `import()` - Preview na upload
  - `importConfirm()` - Definitieve import

### Correctie flow
- `app/Http/Controllers/CoachPortalController.php`
  - `judokasIndex()` - Toont judoka's met "te corrigeren" bovenaan
  - `updateJudoka()` - Slaat wijzigingen op, hervalideert status
- `app/Models/Judoka.php`
  - `hervalideerImportStatus()` - Controleert of alle velden nu correct zijn
  - `isTeCorrigeren()` - Helper voor view

### Email notificaties
- `app/Mail/ImportCorrectieNodig.php` - Email template
- `app/Notifications/ImportWarningNotification.php` - Notification

## Views

- `resources/views/pages/judoka/import.blade.php` - Upload form
- `resources/views/pages/judoka/import-preview.blade.php` - Preview + mapping
- `resources/views/pages/judoka/index.blade.php` - Toont warnings per club
- `resources/views/pages/coach/judokas.blade.php` - Coach portal met "Te corrigeren" sectie

## Email tekst

**Onderwerp:** Judoka gegevens controleren - [Toernooi naam]

**Body:**
```
Beste coach/beheerder van [Club naam],

Bij de import van judoka's voor [Toernooi naam] zijn er [X] judoka's
met ontbrekende of mogelijk incorrecte gegevens gedetecteerd.

Graag controleren en corrigeren via het coach portal:
[Link naar coach portal]

Judoka's die correctie nodig hebben:
- [Naam]: [probleem omschrijving]
- [Naam]: [probleem omschrijving]

Na correctie worden de judoka's automatisch gevalideerd.

Met vriendelijke groet,
[Toernooi naam]
```

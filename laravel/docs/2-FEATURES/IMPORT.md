# Judoka Import

## Overzicht

Import van judoka's via CSV/Excel bestand door de organisator.

## Workflow

1. Organisator uploadt CSV/Excel bestand
2. Systeem analyseert kolommen en toont preview
3. Organisator bevestigt kolom-mapping
4. Systeem importeert judoka's

## Foutafhandeling

### Import fouten (judoka niet aangemaakt)
- Worden getoond in oranje box na import
- **Probleem**: Verdwijnen na page refresh (session-based)
- **TODO**: Opslaan in `toernooien.import_fouten` (JSON veld) voor persistentie

### Import warnings (judoka wel aangemaakt)
- Opgeslagen in `judokas.import_warnings` veld
- Getoond op:
  - **Admin judoka pagina**: Rode box met warnings per club + contactgegevens
  - **Coach portal**: Rode tekst bij individuele judoka

### Wanneer ontstaan warnings?
- Geslacht niet herkend (bv. "X") → standaard M gebruikt
- Gewicht > 100 kg of < 15 kg → "lijkt hoog/laag"
- Leeftijd < 4 of > 50 jaar → "erg jong/hoog"

## Belangrijke regels

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
import_warnings  TEXT NULL  -- Warnings bij import (bv. "Geslacht 'X' niet herkend")
```

### toernooien tabel
```
import_fouten    JSON NULL  -- Fouten van laatste import (judoka's die NIET zijn aangemaakt)
```

## Code locaties

- `app/Services/ImportService.php` - Import logica
  - `importeerDeelnemers()` - Hoofdfunctie
  - `verwerkRij()` - Verwerkt één rij
  - `classificeerJudoka()` - Bepaalt categorie + gewichtsklasse
- `app/Http/Controllers/JudokaController.php` - Import endpoints
  - `importForm()` - Upload formulier
  - `import()` - Preview na upload
  - `importConfirm()` - Definitieve import

## Views

- `resources/views/pages/judoka/import.blade.php` - Upload form
- `resources/views/pages/judoka/import-preview.blade.php` - Preview + mapping
- `resources/views/pages/judoka/index.blade.php` - Toont warnings per club
- `resources/views/pages/coach/judokas.blade.php` - Coach portal met warnings

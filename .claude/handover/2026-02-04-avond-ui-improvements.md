# Session Handover - 4 februari 2026 (avond)

## Overzicht

UI verbeteringen voor poule view, clubs beheer en noodplan prints.

## Wijzigingen

### 1. Poule View - Categorie Scheiding
- **Probleem**: Categorieën (Mini, Jeugd, Dames U15, Heren U15) liepen door elkaar
- **Oplossing**: GroupBy op `categorie_key` i.p.v. `leeftijdsklasse`
- **Bestanden**: `PouleController.php`, `RoleToegang.php`, `poule/index.blade.php`

### 2. Gewogen Indicator Fix
- **Probleem**: Groene stip verscheen bij `gewicht_gewogen = 0` (PHP: `0 !== null` = true)
- **Oplossing**: Check gewijzigd naar `gewicht_gewogen > 0 && aanwezigheid !== 'afwezig'`
- **Bestand**: `poule/index.blade.php` regel 422

### 3. Te Zwaar Waarschuwing (⚠️)
- **Feature**: Judokas die te zwaar zijn voor vaste gewichtsklasse krijgen ⚠️ icoon
- **Bestand**: `poule/index.blade.php`

### 4. Noodplan Print - Landscape
- **Wijziging**: Alle schema prints nu altijd landscape (was: alleen bij 6+ judokas)
- **Bestanden**: `leeg-schema.blade.php`, `poule-schema.blade.php`

### 5. Clubs - WhatsApp Knop
- **Feature**: WhatsApp knop naast PIN met vooringevuld bericht
- **Met telefoonnummer**: Gaat direct naar dat contact (donkergroen)
- **Zonder nummer**: Kies zelf contact (lichtgroen)
- **Telefoon format**: 06... wordt 316... (NL WhatsApp format)
- **Bestand**: `pages/club/index.blade.php`

### 6. Clubs Beheer - UI Verbeteringen
- **Bewerken**: Modal popup i.p.v. inline editing (meer ruimte)
- **Delete**: Rode knop met dubbele bevestiging:
  1. Waarschuwing over judoka's die worden verwijderd
  2. Definitieve bevestiging
- **Bestand**: `organisator/clubs/index.blade.php`, `ClubController.php`

## Geüpdatete Documentatie

- `GEBRUIKERSHANDLEIDING.md` - Clubs sectie uitgebreid
- `handover.md` - Sessie toegevoegd

## Status

- Staging: Alle wijzigingen gedeployed
- Production: Wacht op volledig toernooi test op staging

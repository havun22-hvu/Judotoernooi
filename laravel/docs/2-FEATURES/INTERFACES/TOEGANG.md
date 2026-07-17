---
title: Vrijwilligers & Device Binding
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Vrijwilligers & Device Binding

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Vrijwilligers Database

Organisatoren kunnen vrijwilligers opslaan voor hergebruik tussen toernooien.

### Database

**Tabel:** `vrijwilligers`

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | bigint | PK |
| `organisator_id` | bigint | FK → organisatoren |
| `voornaam` | string | Naam vrijwilliger |
| `telefoonnummer` | string | Voor WhatsApp linkjes |
| `functie` | enum | mat, weging, spreker, dojo, hoofdjury |
| `timestamps` | | created_at, updated_at |

### UI (Instellingen → Organisatie)

Eén link/knop opent popup voor vrijwilligers beheer:

```
┌─────────────────────────────────────────────────┐
│  VRIJWILLIGERS                         [Sluiten]│
├─────────────────────────────────────────────────┤
│  + Nieuwe vrijwilliger                          │
├─────────────────────────────────────────────────┤
│  Jan         06-12345678    Mat       [🗑️]      │
│  Piet        06-87654321    Weging    [🗑️]      │
│  Marie       06-11223344    Spreker   [🗑️]      │
└─────────────────────────────────────────────────┘
```

**Toevoegen:** Inline formulier (voornaam, telefoon, functie dropdown)

### Koppeling met Device Toegang

Bij device toegang aanmaken/bewerken:
1. Dropdown "Selecteer vrijwilliger" (gefilterd op rol)
2. Bij selectie: naam wordt automatisch ingevuld
3. Knoppen per toegang:
   - **📋 URL** - kopieer link naar klembord (bestaand)
   - **📋 PIN** - kopieer pincode naar klembord (bestaand)
   - **WhatsApp** - opent `https://wa.me/{nummer}?text={bericht}` (nieuw, alleen als telefoon bekend)

### WhatsApp Bericht

Template (automatisch gegenereerd):
```
Hoi {voornaam}! Hier is je link voor {rol} op {toernooi_naam}:
{url}
PIN: {pincode}
```

---

## Device Binding voor PWA's

Alle standalone PWA's (Weging, Mat, Spreker, Dojo) vereisen device binding:

### Flow
1. Organisator/Hoofdjury maakt toegang aan (Instellingen → Organisatie)
2. **Optioneel:** Selecteer vrijwilliger uit database → naam + WhatsApp link
3. Vrijwilliger ontvangt URL (via WhatsApp of handmatig)
4. Eerste keer: opent URL → device wordt automatisch gebonden
5. Daarna: device wordt herkend → direct naar interface

### Alleen een echte browser-navigatie bindt

Stap 4 bindt **alleen** als de request `Sec-Fetch-Mode: navigate` stuurt — dat doet een browser
waarin iemand de link opent, en verder niemand. Een client zonder dat signaal krijgt een
bevestigingspagina met een knop ("Dit apparaat koppelen", `POST toegang/{code}/koppel`) en bindt
pas ná die klik.

> **Was kapot (juli 2026):** de link wordt via WhatsApp gedeeld — en WhatsApp haalt elke link
> eerst zelf op om een preview te maken. Die crawler liep door `show()` heen en **claimde de
> binding**; de vrijwilliger kreeg daarna "al aan een ander apparaat gekoppeld" op een link die
> nooit iemand had geopend. Bewijs stond in de nginx-log: `"GET …/toegang/{code}" 302
> "WhatsApp/2.2628.101 W"`, twee minuten vóór de 404 van de echte browser. Google-Read-Aloud deed
> hetzelfde. Elke via WhatsApp gedeelde link was dus eenmalig dood — precies de flow die stap 3
> voorschrijft.

**Waarom `Sec-Fetch-Mode` en niet de User-Agent of `Accept`:** een UA-blacklist mist de volgende
crawler (Signal, Telegram, Slack, …), en WhatsApp stúúrt `Accept: text/html` — het wíl HTML voor de
og-tags. `Sec-Fetch-Mode: navigate` is een positief signaal dat geen enkele link-preview-fetch zet.
Browsers zonder die header (Safari < 16.4) vallen op de knop terug, dus ze kunnen nog steeds koppelen.

**De knop is geen extra beveiliging** — de 12-teken code blijft het geheim. Hij scheidt alleen
"een machine haalt deze URL op" van "een mens opent deze link".

### Beheer (Instellingen → Organisatie)
- Toegangen aanmaken/verwijderen per rol
- Device status zien (gebonden / wachtend)
- Device resetten (nieuw device kan binden)
- Automatische reset bij "Einde toernooi"

### Mat-toegangen volgen het aantal matten

De **mat**-device-toegangen lopen automatisch mee met het aantal matten (Toernooi-tab →
"Aantal Matten"). Er is altijd precies één mat-toegang per bestaande mat.

- **Aantal matten verhogen** → nieuwe mat(ten) + bijbehorende mat-toegang(en) erbij. Geen waarschuwing.
- **Aantal matten verlagen, géén poules op de matten** → overtollige mat(ten) + hun mat-toegang weg.
  Geen waarschuwing.
- **Aantal matten verlagen, wél poules op matten** → eerst een waarschuwing:
  > "Alle poules worden matloos — je moet ze opnieuw indelen via 'Blokken → Verdeel over matten'. Doorgaan?"
  - **OK** → álle matten worden leeg: alle poules krijgen `mat_id = null` (terug naar 'geen mat')
    en hun wedstrijdschema vervalt (regenereert bij opnieuw verdelen). Daarna wordt het nieuwe
    aantal matten toegepast en lopen de mat-toegangen mee.
  - **Annuleren** → er gebeurt niets; het aantal matten blijft staan.
- **Poules worden nooit verwijderd** — alleen losgekoppeld van hun mat (vergelijkbaar met hoe het
  verwijderen van een blok de poules naar het sleepvak verplaatst i.p.v. ze te wissen).
- De device-toegangen-lijst op de Organisatie-tab **ververst direct** na de wijziging.

Implementatie: `ToernooiService::syncMatten()` (matten) roept `syncMatToegangen()` aan; bij een
bevestigde verlaging met poules worden eerst alle matten leeggemaakt.

### Toernooi Instellingen (3 tabs)

**Route:** `/toernooi/{id}/edit`
**View:** `resources/views/pages/toernooi/edit.blade.php`

#### Tab: Toernooi
1. **Basis Gegevens** - Naam, datum, locatie
2. **Wedstrijdschema's** - Volgorde wedstrijden per poulegrootte
3. **Leeftijds- en Gewichtsklassen** - Per categorie:
   - Max leeftijd
   - Naam (label)
   - Geslacht (Gemengd / Jongens / Meisjes)
   - Wedstrijdsysteem (Poules / Kruisfinale / Eliminatie)
   - Max kg verschil (0 = vaste klassen, >0 = dynamische indeling)
   - Gewichtsklassen (alleen bij max kg = 0)
   - Buttons: JBN 2025, JBN 2026

#### Tab: Organisatie
1. **Inschrijving & Portaal** - Hoe komen judoka's in het systeem?
2. **Device Toegangen** - URLs + PINs beheren
3. **Snelkoppelingen** - Pagina Builder + Noodplan
4. **Online Betalingen** - Mollie configuratie
5. **Bloktijden** - Weeg- en starttijden (stappen van 15 min)

---


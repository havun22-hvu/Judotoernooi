# Club Aanmelding via Publieke Pagina

> **Status:** In ontwikkeling
> **Doel:** Clubs kunnen zich aanmelden voor een toernooi via de publieke pagina

## Concept

Clubs die willen deelnemen aan een toernooi kunnen zich aanmelden via de publieke toernooi pagina. De organisator ontvangt de aanmelding en kan de club goedkeuren en uitnodigen.

## Flow

1. **Publieke pagina** → "Aanmelden als club" formulier op de info tab
2. Formulier: clubnaam (verplicht), contactpersoon, email, telefoon
3. Aanmelding wordt opgeslagen als `club_aanmelding` (nieuwe tabel)
4. **Organisator** → ziet aanmeldingen op de "Clubs Uitnodigen" pagina
5. Organisator kan:
   - **Goedkeuren** → club wordt aangemaakt/gekoppeld + portal code/PIN gegenereerd
   - **Afwijzen** → aanmelding verwijderd
6. Bij goedkeuring: organisator kan direct email/WhatsApp sturen met portal link

## Database

### Tabel: `club_aanmeldingen`

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint | PK |
| toernooi_id | FK | Welk toernooi |
| club_naam | string | Naam van de club |
| contact_naam | string | Naam contactpersoon |
| email | string (nullable) | Email adres |
| telefoon | string (nullable) | Telefoonnummer |
| status | enum | pending / goedgekeurd / afgewezen |
| created_at | timestamp | Wanneer aangemeld |

## Routes

| Route | Method | Controller | Beschrijving |
|-------|--------|-----------|-------------|
| `/{org}/{toernooi}/aanmelden` | POST | PubliekController | Club aanmelding opslaan |

## UI

### Publieke pagina (info tab)
- Formulier onderaan de info tab: clubnaam, contactpersoon, email, telefoon
- Succes melding na aanmelden
- Throttle: max 5 aanmeldingen per uur per IP

### Clubs Uitnodigen pagina (organisator)
- Sectie "Aanmeldingen" boven de clublijst
- Per aanmelding: naam, contact, email, telefoon, datum
- Knoppen: Goedkeuren (groen) / Afwijzen (rood)
- Goedkeuren: maakt club aan via `Club::findOrCreateByName()`, koppelt aan toernooi

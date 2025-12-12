# JudoToernooi - Authenticatie & Organisatie Systeem

> **Status:** Planning fase
> **Laatst bijgewerkt:** 2024-12-12
> **Doel:** Complete documentatie voor Claude Code om het project voort te zetten

---

## 1. Rollen Overzicht

### 1.1 Platform Niveau

| Rol | Wie | Verantwoordelijkheid |
|-----|-----|---------------------|
| **Sitebeheerder** | Henk (ontwikkelaar) | Platform beheer, technische support, noodgevallen |

### 1.2 Toernooi Niveau - Organisatie Kant

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Organisator** | Email + Wachtwoord | Hoofdverantwoordelijke, betaalt lease, beheert alles |
| **Hoofdjury** | 5-cijfer PIN | Toegang tot jury functies, dag van toernooi |
| **Weging** | 5-cijfer PIN | Weeg-interface, dag van toernooi |
| **Mat** | 5-cijfer PIN | Mat-interface per mat, dag van toernooi |
| **Spreker** | 5-cijfer PIN | Omroep-interface, dag van toernooi |

### 1.3 Toernooi Niveau - Uitnodigingen Kant

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Coach** | 5-cijfer PIN + gedeelde URL | Beheert judoka's van eigen club |

---

## 2. Authenticatie Systemen

### 2.1 Organisator Login

```
Type: Email + Wachtwoord
Locatie: /login of /organisator/login
Features:
  - Wachtwoord vergeten via email
  - Preview modus tot betaling
  - Volledige toegang na betaling
```

**Database: `organisators` tabel**
```
- id
- naam
- email (unique)
- telefoon
- wachtwoord_hash
- email_verified_at
- laatste_login
- created_at / updated_at
```

### 2.2 Vrijwilligers PIN Systeem

```
Type: 5-cijfer PIN per rol
Beheer: Organisator stelt in via Instellingen
Delen: Organisator mailt/appt PINs naar vrijwilligers
```

**Opslag: Op `toernooien` tabel**
```
- pin_hoofdjury (5 chars)
- pin_weging (5 chars)
- pin_mat (5 chars)
- pin_spreker (5 chars)
```

**UI in Instellingen:**
```
┌─────────────────────────────────────────────────┐
│ Vrijwilligers PIN codes                         │
├─────────────────────────────────────────────────┤
│ Hoofdjury:  [34521] [📋 Kopieer] [🔄 Nieuwe]    │
│ Weging:     [89012] [📋 Kopieer] [🔄 Nieuwe]    │
│ Mat:        [45678] [📋 Kopieer] [🔄 Nieuwe]    │
│ Spreker:    [23456] [📋 Kopieer] [🔄 Nieuwe]    │
└─────────────────────────────────────────────────┘
```

### 2.3 Coach PIN Systeem

```
Type: 5-cijfer PIN + gedeelde portal_code (12 chars)
URL: /school/{portal_code}
Max: 3 coaches per club per toernooi
PIN identificeert welke coach inlogt
```

**Database: `coaches` tabel (bestaat al)**
```
- id
- club_id
- toernooi_id
- portal_code (12 chars, gedeeld per club+toernooi)
- naam
- email (optioneel)
- telefoon (optioneel)
- pincode (5 chars)
- laatst_ingelogd_op
```

---

## 3. Lease & Betaling Model

### 3.1 Lease Structuur

```
- Lease is PER TOERNOOI
- Organisator kan meerdere toernooien leasen
- Settings (judoschool emails etc.) bewaren per organisator voor volgend jaar
```

### 3.2 Betaling

```
Nederland: Mollie
Internationaal: Via Havuncore (nog te bepalen)
```

### 3.3 Preview Modus

```
Voor betaling:
  - Organisator kan systeem verkennen
  - Beperkte functionaliteit
  - Watermark of banner "Preview"

Na betaling:
  - Volledige toegang
  - Toernooi actief
```

---

## 4. Publieke Pagina's (Ouders/Toeschouwers)

### 4.1 Doel

Ouders kunnen zien:
- In welke poule hun judoka zit
- Op welke mat
- Wanneer ze aan de beurt zijn
- Live uitslagen

### 4.2 Toegang

```
URL: /toernooi/{slug}/live of /live/{toernooi_code}
Geen login nodig
Zoekfunctie op judoka naam
```

### 4.3 Features

```
- Zoek judoka op naam
- Toon poule, mat, volgorde
- Live status (bezig, klaar, wachtend)
- Uitslagen per poule
```

---

## 5. Print/Backup Systeem

### 5.1 Waarom

Papieren backup voor als techniek faalt op wedstrijddag.

### 5.2 Print Functionaliteit

| Wat | Wie print | Locatie |
|-----|-----------|---------|
| Poules overzicht | Organisator/Hoofdjury | Poule pagina |
| Weeglijst | Weging | Weging pagina |
| Weegkaarten | Coaches zelf | Coach portal |
| Lege wedstrijdschemas | Organisator | Instellingen of Blok pagina |

### 5.3 Lege Wedstrijdschemas

```
Printbare templates voor:
- 2 judokas (1 wedstrijd)
- 3 judokas (3 wedstrijden)
- 4 judokas (6 wedstrijden)
- 5 judokas (10 wedstrijden)
- 6 judokas (15 wedstrijden)
- 7 judokas (21 wedstrijden)

Inclusief: WP/JP kolommen, handmatig invulbaar
```

---

## 6. Chat Systeem (Fase 2 - Havuncore)

### 6.1 Doel

Real-time communicatie tijdens wedstrijddag.

### 6.2 Deelnemers

```
- Organisator
- Hoofdjury
- Weging
- Mat medewerkers
- Spreker
- Sitebeheerder (voor noodgevallen)
- AI assistent (voor veelgestelde vragen)
```

### 6.3 Kanalen

```
- Algemeen
- Per mat (Mat 1, Mat 2, etc.)
- Weging
- Hoofdjury
- Support (met sitebeheerder)
```

### 6.4 Implementatie

Via Havuncore - nog te bouwen.

---

## 7. QR App Login (Fase 2 - Havuncore)

### 7.1 Concept

```
1. Coach installeert PWA op telefoon
2. Website toont QR code
3. Coach scant met app
4. Website is ingelogd
```

### 7.2 Status

Optionele upgrade, PIN blijft altijd werken als fallback.

---

## 8. Bouwvolgorde

### Fase 1: Basis Authenticatie (NU)

```
1.1 [ ] Organisator account systeem
    - Registratie met email + wachtwoord
    - Login/Logout
    - Wachtwoord vergeten

1.2 [ ] Vrijwilligers PIN in instellingen
    - PIN codes per rol in toernooi settings
    - Kopieer knoppen
    - Regenereer knoppen

1.3 [ ] Coach systeem opschonen
    - Huidige implementatie werkt
    - Eventueel kleine fixes
```

### Fase 2: Publieke Pagina's

```
2.1 [ ] Ouder/Toeschouwer pagina
    - Zoek judoka
    - Toon poule/mat/volgorde
    - Live uitslagen
```

### Fase 3: Print Functionaliteit

```
3.1 [ ] Print knoppen toevoegen
    - Poules pagina
    - Weeglijst
    - Weegkaarten (coach portal)

3.2 [ ] Lege wedstrijdschema templates
    - PDF of print-ready HTML
    - 2-7 judokas varianten
```

### Fase 4: Lease & Betaling

```
4.1 [ ] Preview modus implementeren
4.2 [ ] Mollie integratie (via Havuncore?)
4.3 [ ] Organisator settings bewaren voor volgend jaar
```

### Fase 5: Havuncore Integraties

```
5.1 [ ] Chat systeem
5.2 [ ] QR App login (optioneel)
5.3 [ ] Internationale betalingen
```

---

## 9. Database Wijzigingen Nodig

### Nieuwe Tabellen

```sql
-- Organisators (hoofdgebruikers)
CREATE TABLE organisators (
    id BIGINT PRIMARY KEY,
    naam VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    telefoon VARCHAR(20),
    wachtwoord_hash VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    laatste_login TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Koppeling organisator <-> toernooi
CREATE TABLE organisator_toernooi (
    organisator_id BIGINT,
    toernooi_id BIGINT,
    rol ENUM('eigenaar', 'beheerder'),
    PRIMARY KEY (organisator_id, toernooi_id)
);
```

### Wijzigingen Bestaande Tabellen

```sql
-- Toernooien: vrijwilligers PINs toevoegen
ALTER TABLE toernooien ADD COLUMN pin_hoofdjury VARCHAR(5);
ALTER TABLE toernooien ADD COLUMN pin_weging VARCHAR(5);
ALTER TABLE toernooien ADD COLUMN pin_mat VARCHAR(5);
ALTER TABLE toernooien ADD COLUMN pin_spreker VARCHAR(5);
```

---

## 10. Huidige Stand van Zaken

### Wat al werkt:

- ✅ Coach PIN systeem (5 cijfers)
- ✅ Gedeelde portal_code per club (12 chars)
- ✅ Club management pagina met coaches
- ✅ Bestaande rol-login (admin/jury/weging/mat/spreker) met wachtwoorden

### Wat nog moet:

- ❌ Organisator account systeem
- ❌ Vrijwilligers PIN in instellingen
- ❌ Publieke ouder pagina
- ❌ Print functionaliteit
- ❌ Preview/betaling modus

---

## 11. Voor Claude Code bij Nieuwe Sessie

### Belangrijke bestanden:

```
app/Models/Coach.php              - Coach model met PIN/portal_code
app/Models/Club.php               - Club met coaches relatie
app/Http/Controllers/CoachPortalController.php - PIN login logica
app/Http/Controllers/ClubController.php - Coach beheer
resources/views/pages/club/index.blade.php - Club/coach management UI
resources/views/pages/coach/login-pin.blade.php - PIN login pagina
routes/web.php                    - Routes voor /school/{code}
```

### Terminologie:

```
Sitebeheerder = Henk (ontwikkelaar, jij praat met hem)
Organisator = Toernooi organiserende club (betaalt lease)
Admin = Synoniem voor Organisator (NIET sitebeheerder)
Coach = Trainer van uitgenodigde club (beheert judoka's)
Vrijwilligers = Hoofdjury, Weging, Mat, Spreker (alleen wedstrijddag)
```

### Niet verwarren:

```
❌ Coach ≠ iemand aan organisatie kant
✅ Coach = trainer van UITGENODIGDE club die judoka's aanmeldt

❌ Admin ≠ Sitebeheerder
✅ Admin = Organisator van het toernooi
```

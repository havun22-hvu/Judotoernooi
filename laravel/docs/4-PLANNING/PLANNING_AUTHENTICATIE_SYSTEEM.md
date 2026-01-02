# JudoToernooi - Authenticatie & Organisatie Systeem

> **Status:** Implementatie fase
> **Laatst bijgewerkt:** 2024-12-13
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
| **Hoofdjury** | Geheime URL | Toegang tot jury functies, dag van toernooi |
| **Weging** | Geheime URL | Weeg-interface, dag van toernooi |
| **Mat** | Geheime URL | Mat-interface per mat, dag van toernooi |
| **Spreker** | Geheime URL | Omroep-interface, dag van toernooi |

### 1.3 Toernooi Niveau - Uitnodigingen Kant

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Coach** | Gedeelde URL + 5-cijfer PIN | Beheert judoka's van eigen club |

---

## 2. URL Structuur

### 2.1 Overzicht

```
judotournament.org/                     → Homepage (publiek)
judotournament.org/organisator/login    → Organisator login
judotournament.org/organisator/dashboard → Organisator dashboard

judotournament.org/team/{12-char-code}  → Vrijwilliger toegang (redirect)
judotournament.org/weging               → Weging interface (na redirect)
judotournament.org/mat                  → Mat selectie (na redirect)
judotournament.org/jury                 → Hoofdjury interface (na redirect)
judotournament.org/spreker              → Spreker interface (na redirect)

judotournament.org/school/{12-char-code} → Coach portal
judotournament.org/live/{slug}          → Publieke pagina (ouders)
```

### 2.2 Vrijwilligers Geheime URLs

Elke rol krijgt een unieke 12-karakter code. De code verdwijnt uit de adresbalk na klikken.

**Flow:**
```
1. Organisator deelt link: "Klik hier: Weging" → /team/Abc123xxxYyy
2. Vrijwilliger klikt
3. Sessie onthoudt: toernooi_id + rol
4. Redirect naar /weging
5. Adresbalk toont: judotournament.org/weging (code weg!)
```

**Database: `toernooien` tabel**
```
- code_hoofdjury (12 chars, unique)
- code_weging (12 chars, unique)
- code_mat (12 chars, unique)
- code_spreker (12 chars, unique)
```

**Beveiliging:**
- Elke rol heeft EIGEN geheime code
- Weger kan niet bij hoofdjury (andere code)
- 54^12 = 1.2 × 10²¹ mogelijke codes (onmogelijk te raden)
- Code alleen zichtbaar in gedeelde link, niet in browser

---

## 3. Authenticatie Systemen

### 3.1 Organisator Login ✅ GEBOUWD

```
Type: Email + Wachtwoord
URL: /organisator/login
Features:
  - Registratie
  - Login/Logout
  - Wachtwoord vergeten (email)
  - Remember me functie
```

**Database: `organisators` tabel**
```sql
CREATE TABLE organisators (
    id BIGINT PRIMARY KEY,
    naam VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    telefoon VARCHAR(20),
    password VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    laatste_login TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE organisator_toernooi (
    organisator_id BIGINT,
    toernooi_id BIGINT,
    rol ENUM('eigenaar', 'beheerder'),
    PRIMARY KEY (organisator_id, toernooi_id)
);
```

### 3.2 Vrijwilligers Geheime Links ✅ GEBOUWD

```
Type: Geheime URL per rol (geen wachtwoord/PIN nodig)
URL: /team/{code} → redirect naar /weging, /mat, /jury, of /spreker
Beheer: Organisator kopieert links in Instellingen → Organisatie tab
```

### 3.3 Coach PIN Systeem ✅ GEBOUWD

```
Type: Gedeelde URL + 5-cijfer PIN
URL: /school/{portal_code}
Max: 3 coaches per club per toernooi
PIN identificeert welke coach inlogt
```

**Database: `coaches` tabel**
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

## 4. Instellingen Pagina

### 4.1 Tab Structuur ✅ GEBOUWD

**Tab: Toernooi**
- Algemeen (naam, datum, locatie)
- Inschrijving (deadline, max deelnemers)
- Matten & Blokken
- Poule instellingen
- Weging
- Gewichtsklassen

**Tab: Organisatie**
- Vrijwilligers Links (kopieer knoppen per rol)
- Bloktijden
- Wachtwoorden (legacy)

---

## 5. Publieke Pagina's (Ouders/Toeschouwers) ❌ NOG BOUWEN

### 5.1 Doel

Ouders kunnen zien:
- In welke poule hun judoka zit
- Op welke mat
- Wanneer ze aan de beurt zijn
- Live uitslagen

### 5.2 Toegang

```
URL: /live/{toernooi-slug}
Geen login nodig
Zoekfunctie op judoka naam
```

---

## 6. Homepage ❌ NOG BOUWEN

### 6.1 Elementen

```
- Logo JudoToernooi
- Korte uitleg wat het platform doet
- "Inloggen als Organisator" knop
- "Ik ben Coach" knop (met uitleg over link)
- Footer met contact/support info
```

---

## 7. Bouwvolgorde

### Fase 1: Basis Authenticatie ✅ KLAAR

```
1.1 [x] Organisator account systeem
    - Registratie met email + wachtwoord
    - Login/Logout
    - Wachtwoord vergeten

1.2 [x] Vrijwilligers geheime URLs
    - Unieke code per rol per toernooi
    - Redirect naar generieke URLs
    - Code verdwijnt uit adresbalk

1.3 [x] Instellingen tabs
    - Toernooi tab
    - Organisatie tab met kopieer knoppen

1.4 [x] Coach systeem
    - Gedeelde portal_code per club
    - PIN login per coach
```

### Fase 2: Homepage & Publiek

```
2.1 [ ] Homepage met logo/uitleg
2.2 [ ] Publieke pagina voor ouders (/live/{slug})
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
4.2 [ ] Mollie integratie (via Havuncore)
4.3 [ ] Organisator settings bewaren voor volgend jaar
```

### Fase 5: Havuncore Integraties

```
5.1 [ ] Chat systeem
5.2 [ ] QR App login (optioneel)
5.3 [ ] Internationale betalingen
```

---

## 8. Belangrijke Bestanden

### Controllers

```
app/Http/Controllers/OrganisatorAuthController.php  - Organisator login/register
app/Http/Controllers/RoleToegang.php                - Vrijwilligers geheime URLs
app/Http/Controllers/CoachPortalController.php      - Coach PIN login
app/Http/Controllers/ClubController.php             - Club/coach beheer
app/Http/Controllers/ToernooiController.php         - Toernooi CRUD + dashboard
```

### Models

```
app/Models/Organisator.php   - Authenticatable model voor organisators
app/Models/Toernooi.php      - Toernooi met role codes
app/Models/Coach.php         - Coach met PIN/portal_code
app/Models/Club.php          - Club met coaches relatie
```

### Middleware

```
app/Http/Middleware/CheckRolSessie.php  - Controleert rol sessie voor /weging etc.
```

### Views

```
resources/views/organisator/auth/login.blade.php
resources/views/organisator/auth/register.blade.php
resources/views/organisator/dashboard.blade.php
resources/views/pages/toernooi/edit.blade.php       - Instellingen met tabs
resources/views/pages/coach/login-pin.blade.php     - Coach PIN login
```

### Routes

```
routes/web.php:
  /organisator/*           - Organisator auth routes
  /team/{code}             - Vrijwilliger toegang
  /weging, /mat, /jury, /spreker - Generieke rol interfaces
  /school/{code}           - Coach portal
```

---

## 9. Terminologie

```
Sitebeheerder = Henk (ontwikkelaar, platform eigenaar)
Organisator   = Toernooi organiserende club (betaalt lease)
Admin         = Synoniem voor Organisator (NIET sitebeheerder)
Coach         = Trainer van UITGENODIGDE club (beheert judoka's)
Vrijwilligers = Hoofdjury, Weging, Mat, Spreker (alleen wedstrijddag)
```

### Niet verwarren:

```
❌ Coach ≠ iemand aan organisatie kant
✅ Coach = trainer van UITGENODIGDE club die judoka's aanmeldt

❌ Admin ≠ Sitebeheerder
✅ Admin = Organisator van het toernooi
```

---

## 10. Volgende Stappen

1. **Homepage bouwen** met logo, uitleg en login knoppen
2. **Publieke pagina** voor ouders (/live/{slug})
3. **Email configuratie** voor wachtwoord reset
4. **Print functionaliteit** toevoegen

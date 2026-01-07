# JudoToernooi - Authenticatie & Device Binding Systeem

> **Status:** Planning bijgewerkt
> **Laatst bijgewerkt:** 2026-01-04
> **Zie ook:** `ROLLEN_HIERARCHIE.md` voor complete rolbeschrijvingen

---

## 1. Rollen Overzicht

### 1.1 Platform Niveau

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Superadmin** | Wachtwoord (prod) / PIN (dev) | Henk, technische beheer |

### 1.2 Toernooi Niveau - Met Wachtwoord

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Organisator** | Email + wachtwoord | Leased site, volledige toegang + financieel |
| **Beheerders** | Email + wachtwoord | Toegevoegd door organisator, geen financieel |

### 1.3 Toernooi Niveau - Met Device Binding

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Hoofdjury** | URL + PIN + device | Toezicht, mag device management |
| **Mat** | URL + PIN + device | Per mat, wedstrijden afhandelen |
| **Weging** | URL + PIN + device | Judoka's wegen |
| **Spreker** | URL + PIN + device | Omroepen |
| **Dojo** | URL + PIN + device | Toegangscontrole |

### 1.4 Uitnodigingen Kant

| Rol | Authenticatie | Beschrijving |
|-----|---------------|--------------|
| **Coach** | URL + 5-cijfer PIN | Judoka's aanmelden, max 3 per club |
| **Coachkaart** | Device binding + foto | Fysieke toegang dojo |

---

## 2. URL Structuur

```
judotournament.org/                     → Homepage (publiek)
judotournament.org/login                → Organisator/Beheerder login

judotournament.org/toegang/{code}       → Device binding flow (nieuw!)
  → PIN invoeren → device binden → redirect naar interface

judotournament.org/weging/{toegang_id}  → Weging interface (device-gebonden)
judotournament.org/mat/{toegang_id}     → Mat interface (device-gebonden)
judotournament.org/jury/{toegang_id}    → Hoofdjury interface (device-gebonden)
judotournament.org/spreker/{toegang_id} → Spreker interface (device-gebonden)
judotournament.org/dojo/{toegang_id}    → Dojo scanner (device-gebonden)

judotournament.org/school/{code}        → Coach portal
judotournament.org/live/{slug}          → Publieke pagina (ouders)
```

---

## 3. Device Binding Systeem

### 3.1 Flow

```
1. Organisator maakt toegang aan (Instellingen → Organisatie)
   → Systeem genereert: unieke URL + 4-cijfer PIN

2. Organisator/Hoofdjury deelt URL + PIN met vrijwilliger
   → Via WhatsApp, email, mondeling, etc.

3. Vrijwilliger opent URL op device
   → Ziet PIN invoerveld

4. Vrijwilliger voert PIN in
   → Device token wordt gegenereerd
   → Token opgeslagen: localStorage + server (device_toegangen tabel)
   → Redirect naar interface

5. Volgende keer: device herkend via token
   → Direct naar interface (geen PIN nodig)

6. Token verloren (browser data gewist)?
   → PIN opnieuw invoeren → nieuwe binding
```

### 3.2 Database: `device_toegangen` tabel

```sql
CREATE TABLE device_toegangen (
    id BIGINT PRIMARY KEY,
    toernooi_id BIGINT NOT NULL,

    -- Vrijwilliger gegevens
    naam VARCHAR(255) NOT NULL,       -- naam vrijwilliger
    telefoon VARCHAR(20) NULL,        -- telefoonnummer
    email VARCHAR(255) NULL,          -- email adres

    -- Rol en toegang
    rol ENUM('hoofdjury', 'mat', 'weging', 'spreker', 'dojo'),
    mat_nummer INT NULL,              -- alleen voor rol='mat'
    code VARCHAR(12) UNIQUE,          -- unieke URL code
    pincode VARCHAR(4),               -- 4-cijfer PIN

    -- Device binding
    device_token VARCHAR(64) NULL,    -- gebonden device
    device_info VARCHAR(255) NULL,    -- "iPhone Safari" etc.
    gebonden_op TIMESTAMP NULL,
    laatst_actief TIMESTAMP NULL,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 3.3 Beheer UI (Instellingen → Organisatie)

**Vrijwilligers overzicht** - Tabel met alle vrijwilligers:

| Naam | Telefoon | Email | Rol | Status | Acties |
|------|----------|-------|-----|--------|--------|
| Jan de Vries | 06-12345678 | jan@email.nl | Mat 1 | ✓ Gebonden | [URL] [Reset] [Bewerk] [Verwijder] |
| Piet Jansen | 06-87654321 | - | Weging | ⏳ Wacht | [URL] [Reset] [Bewerk] [Verwijder] |
| Marie Bakker | - | marie@email.nl | Spreker | ✓ Gebonden | [URL] [Reset] [Bewerk] [Verwijder] |

```
[+ Vrijwilliger toevoegen]

Modal: Vrijwilliger toevoegen/bewerken
├── Naam: [____________] (verplicht)
├── Telefoon: [____________]
├── Email: [____________]
├── Rol: [dropdown: Hoofdjury/Mat/Weging/Spreker/Dojo]
└── Mat nummer: [__] (alleen bij rol=Mat)
```

**Acties:**
- **URL:** Kopieert volledige URL + PIN naar clipboard
- **Reset:** Wist device binding, nieuw device kan binden met zelfde PIN
- **Bewerk:** Wijzig naam, telefoon, email, rol
- **Verwijder:** Verwijdert vrijwilliger volledig

**Wie mag dit:**
- Organisator: alles
- Hoofdjury: alles (behalve eigen toegang verwijderen)

---

## 4. Coachkaart Device Binding

### 4.1 Probleem
QR-codes makkelijk te delen (screenshot, doorsturen) → ongeautoriseerde toegang dojo

### 4.2 Oplossing

```
1. Coach ontvangt coachkaart link

2. Opent link op telefoon
   → Device wordt gebonden (eerste keer)

3. Keuze: Upload pasfoto OF Maak selfie
   → Foto wordt opgeslagen

4. QR-code wordt zichtbaar
   → Alleen op dit device

5. Bij dojo-ingang: scan QR
   → Dojo-scanner toont foto
   → Vrijwilliger vergelijkt gezicht met persoon
```

### 4.3 Database: `coach_kaarten` tabel (uitbreiding)

```sql
ALTER TABLE coach_kaarten ADD COLUMN device_token VARCHAR(64) NULL;
ALTER TABLE coach_kaarten ADD COLUMN device_info VARCHAR(255) NULL;
ALTER TABLE coach_kaarten ADD COLUMN gebonden_op TIMESTAMP NULL;
```

---

## 5. Organisator Login

### 5.1 Bestaand systeem (behouden)

```
Type: Email + Wachtwoord
URL: /login
Features:
  - Registratie
  - Login/Logout
  - Wachtwoord vergeten (email)
  - Remember me functie
```

### 5.2 Beheerders toevoegen (nieuw)

```
Organisator kan emails toevoegen:
  Instellingen → Organisatie → Beheerders

  - Email invoeren
  - Uitnodiging wordt verstuurd
  - Beheerder maakt account aan
  - Krijgt toegang tot dit toernooi (zonder financieel)
```

---

## 6. Superadmin Login

### 6.1 Production
- Reguliere login met email + wachtwoord
- Alleen henkvu@gmail.com

### 6.2 Local/Staging
- Simpele PIN (4-cijfer)
- Snelle toegang voor development/testing

---

## 7. Einde Toernooi

Wanneer organisator "Einde toernooi" triggert:

1. **Alle device bindings worden gereset**
   - `device_toegangen.device_token = NULL`
   - `coach_kaarten.device_token = NULL`

2. **Statistieken worden berekend**
   - Aantal wedstrijden
   - Aantal deelnemers
   - Resultaten per club
   - etc.

3. **Statistieken worden getoond**
   - Op organisator dashboard
   - Op publieke pagina

---

## 8. Te Verwijderen

- ~~Service Login pagina~~ (`pages/auth/service-login.blade.php`)
- ~~Toernooi-level wachtwoorden per rol~~

---

## 9. Belangrijke Bestanden

### Controllers (nieuw/aan te passen)

```
app/Http/Controllers/DeviceToegangController.php   - Device binding flow
app/Http/Controllers/OrganisatieController.php     - Toegangen beheer UI
```

### Models (nieuw)

```
app/Models/DeviceToegang.php   - Device toegang records
```

### Middleware (nieuw)

```
app/Http/Middleware/CheckDeviceBinding.php  - Controleert device token
```

### Views (nieuw/aan te passen)

```
resources/views/pages/toegang/pin.blade.php        - PIN invoer pagina
resources/views/pages/toernooi/organisatie.blade.php - Toegangen beheer
```

### Routes

```
routes/web.php:
  /login                    - Organisator/Beheerder login
  /toegang/{code}           - Device binding entry point
  /toegang/{code}/verify    - PIN verificatie
  /api/toegang/reset/{id}   - Reset device binding
```

---

## 10. Bouwvolgorde

### Standaard toegangen bij nieuw toernooi

Bij het aanmaken van een nieuw toernooi worden automatisch de volgende toegangen aangemaakt:
- 1x Hoofdjury
- 1x Mat (Mat 1)
- 1x Weging
- 1x Spreker
- 1x Dojo

Dit geeft de organisator direct een werkende setup zonder handmatig toegangen te moeten aanmaken.

### Fase 1: Database & Models
- [x] Migration voor `device_toegangen` tabel
- [x] DeviceToegang model
- [x] Coach_kaarten migration uitbreiding

### Fase 2: Device Binding Flow
- [ ] Route `/toegang/{code}`
- [ ] PIN invoer view
- [ ] Binding logica (token genereren, opslaan)
- [ ] Middleware voor device check

### Fase 3: Beheer UI
- [ ] Organisatie tab uitbreiden
- [ ] Toegangen CRUD
- [ ] Kopieer/Reset/Verwijder acties

### Fase 4: Coachkaart Uitbreiding
- [ ] Device binding bij activatie
- [ ] Foto upload/selfie
- [ ] QR alleen tonen als gebonden + foto

### Fase 5: Cleanup
- [ ] Service Login verwijderen
- [ ] Oude wachtwoord velden verwijderen

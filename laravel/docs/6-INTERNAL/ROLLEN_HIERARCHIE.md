# Rollen Hierarchie - JudoToernooi

> **Laatst bijgewerkt:** 2026-01-04

---

## Platform Niveau

### Superadmin (Sitebeheerder)
- **Wie:** Henk (henkvu@gmail.com)
- **Toegang:** Passe-partout - overal toegang
- **Taken:**
  - Technische problemen oplossen
  - Software onderhoud
  - Los van specifiek toernooi
- **Authenticatie:**
  - Production: email + wachtwoord
  - Local/Staging: simpele pincode

---

## Toernooi Niveau - Organisatie Kant

### Organisator
- **Wie:** Leased de site, hoofdverantwoordelijke
- **Toegang:** Alles inclusief financieel
- **Taken:**
  - Wettelijke overeenkomst (contractpartij)
  - Betalingen (lease)
  - Toernooi configuratie
  - Device management (URLs + PINs uitdelen)
- **Authenticatie:** Email + wachtwoord
- **KYC:** Ja, verplicht (naam, adres, KvK indien van toepassing)

### Beheerders
- **Wie:** Extra helpers toegevoegd door organisator
- **Toegang:** Alles BEHALVE financieel
- **Taken:** Helpen met administratie
- **Authenticatie:** Email + wachtwoord (toegevoegd door organisator)

### Hoofdjury
- **Wie:** Toernooi leiding op de dag zelf
- **Toegang:** Alles BEHALVE financieel + device management
- **Taken:**
  - Overzien of alles goed gaat
  - Bijsturen waar nodig
  - Beslissingen bij problemen
  - URLs + PINs uitdelen aan gastrollen
- **Authenticatie:** URL + PIN + device binding
- **Beheer:** Aangemaakt door organisator in Instellingen → Organisatie

---

## Toernooi Niveau - Gastrollen (dag van toernooi)

> Kunnen NIET bij instellingen, alleen hun eigen interface

### Mat
- **Taken:** Wedstrijden afhandelen, scores invoeren
- **Authenticatie:** URL + PIN + device binding
- **Toegang:** Alleen mat interface (gekoppeld aan specifieke mat)
- **Beheer:** Instellingen → Organisatie → Mat toegangen

### Weging
- **Taken:** Judoka's wegen, gewicht registreren
- **Authenticatie:** URL + PIN + device binding
- **Toegang:** Alleen weging interface
- **Beheer:** Instellingen → Organisatie → Weging toegangen

### Spreker
- **Taken:** Omroepen, volgende wedstrijden aankondigen
- **Authenticatie:** URL + PIN + device binding
- **Toegang:** Alleen spreker interface
- **Beheer:** Instellingen → Organisatie → Spreker toegangen

### Dojo Scanner
- **Taken:** Toegang controleren bij ingang dojo
- **Authenticatie:** URL + PIN + device binding
- **Toegang:** Alleen scanner interface
- **Beheer:** Instellingen → Organisatie → Dojo toegangen

---

## Uitnodigingen Kant

### Judoschool (Club)
- **Wie:** De uitgenodigde club/vereniging
- **Taken:**
  - Ontvangt links en wachtwoorden
  - Beheert eigen coaches
  - Betalingen
- **Authenticatie:** Portal link per club

### Coach (Coachpage)
- **Wie:** Trainer van uitgenodigde club
- **Taken:**
  - Judoka's aanmelden/beheren
  - Weegkaarten bekijken/delen (pas beschikbaar na blokverdeling)
  - Coachkaarten beheren
- **Authenticatie:** Gedeelde URL + 5-cijfer PIN
- **Beheer:** Judoschool regelt dit zelf (max 3 coaches per club)

### Coachkaart (Wedstrijdcoach)
- **Wie:** Coach die op de dag aanwezig is in de dojo
- **Taken:** Judoka's begeleiden tijdens wedstrijden
- **Toegang:** Dojo betreden met coachkaart
- **Authenticatie:**
  - Device binding (activeren op 1 telefoon)
  - Pasfoto uploaden OF selfie maken
  - QR-code pas zichtbaar na foto
- **Verificatie:** Dojo-scanner toont foto, vrijwilliger vergelijkt gezicht

---

## Judoka's (Deelnemers)

- **Wie:** De wedstrijddeelnemers
- **Aangemeld door:** Coach via coachpage
- **Identificatie:** Weegkaart met QR code
- **Geen login:** Geen eigen account nodig

---

## Device Binding Systeem

### Werking
1. Organisator/Hoofdjury maakt toegang aan (URL + PIN)
2. Vrijwilliger opent URL, voert PIN in
3. Device wordt gebonden (token in localStorage + server)
4. Alleen dat device heeft toegang
5. Token verloren? → PIN opnieuw invoeren

### Beheer (Instellingen → Organisatie)
- Toegangen aanmaken per rol
- Device status zien (gebonden / wachtend)
- Device resetten (nieuw device kan binden)
- Automatische reset bij "Einde toernooi"

### Einde Toernooi
- Alle device bindings worden gereset
- Statistieken worden berekend en getoond
- Handmatig of automatisch te triggeren

---

## Toegangsmatrix

| Functie | Superadmin | Organisator | Beheerder | Hoofdjury | Mat | Weging | Spreker | Dojo |
|---------|------------|-------------|-----------|-----------|-----|--------|---------|------|
| **Financieel** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Device management** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Instellingen** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Noodplan** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Poules beheer** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Mat interface** | ✅ | 👁️ | 👁️ | 👁️ | ✅ | ❌ | ❌ | ❌ |
| **Weging interface** | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ✅ | ❌ | ❌ |
| **Spreker interface** | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ✅ | ❌ |
| **Dojo scanner** | ✅ | 👁️ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ✅ |

✅ = Volledige toegang | 👁️ = Alleen bekijken | ❌ = Geen toegang

---

## Terminologie

| Term | Rol | Beschrijving |
|------|-----|--------------|
| **Superadmin** | Platform | Henk (henkvu@gmail.com), technisch beheer |
| **Sitebeheerder** | Platform | Synoniem voor Superadmin |
| **Organisator** | Toernooi | Leased de site, volledige toegang + financieel |
| **Beheerder** | Toernooi | Helper toegevoegd door organisator (geen financieel) |
| **Hoofdjury** | Toernooi | Toezicht op wedstrijddag, device-gebonden |
| **Gastrol** | Toernooi | Mat, Weging, Spreker, Dojo - device-gebonden |
| **Mat** | Gastrol | Wedstrijden afhandelen, specifieke mat |
| **Weging** | Gastrol | Judoka's wegen |
| **Spreker** | Gastrol | Omroepen prijsuitreikingen |
| **Dojo** | Gastrol | Toegangscontrole met scanner |
| **Club** | Uitnodiging | Uitgenodigde judoschool/vereniging |
| **Coach** | Uitnodiging | Trainer van club, beheert judoka's |
| **Coachkaart** | Uitnodiging | Fysieke toegang dojo, device + foto |
| **Judoka** | Deelnemer | Wedstrijddeelnemer, geen login |
| **Vrijwilliger** | Algemeen | Iedereen met gastrol (Mat, Weging, etc.) |
| **Device binding** | Technisch | Koppeling tussen toegang en specifiek apparaat |
| **Toegang** | Technisch | URL + PIN combinatie voor een rol |

### Niet verwarren

| Fout | Correct |
|------|---------|
| Admin = Sitebeheerder | Admin = Organisator van toernooi |
| Coach = iemand aan organisatie kant | Coach = trainer van UITGENODIGDE club |
| Hoofdjury = organisator | Hoofdjury = toezicht, geen financieel |

---

## Notities

- **Superadmin** is altijd passe-partout (technische bypass)
- **Organisator** = enige met financiële toegang
- **Beheerders** = door organisator toegevoegde helpers (geen financieel)
- **Hoofdjury** = device-gebonden, mag wel URLs/PINs uitdelen
- **Gastrollen** zijn device-gebonden en alleen actief op toernooidag
- **Coachkaart** = device-gebonden + foto verificatie tegen delen

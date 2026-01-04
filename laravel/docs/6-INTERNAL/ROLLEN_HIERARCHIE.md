# Rollen Hiërarchie - JudoToernooi

> **Laatst bijgewerkt:** 2024-12-17

---

## Platform Niveau

### Admin (Sitebeheerder)
- **Wie:** Henk (ontwikkelaar)
- **Toegang:** Passe-partout - overal toegang
- **Taken:**
  - Technische problemen oplossen
  - Software onderhoud
  - Los van specifiek toernooi
- **Authenticatie:** Eigen admin login

---

## Toernooi Niveau - Organisatie Kant

### Organisator
- **Wie:** Eerste aanspreekpunt, betaalt de rekening
- **Toegang:** Alle tabs zichtbaar, instellingen regelen
- **Taken:**
  - Wettelijke overeenkomst (contractpartij)
  - Betalingen (lease)
  - Toernooi configuratie
- **Authenticatie:** Email + wachtwoord
- **KYC:** Ja, verplicht (naam, adres, KvK indien van toepassing)
- **Voert NIET uit:** Mat/weging/spreker acties

### Hoofdjury
- **Wie:** Toernooi leiding op de dag zelf
- **Toegang:** Alle tabs zichtbaar, instellingen regelen
- **Taken:**
  - Overzien of alles goed gaat
  - Bijsturen waar nodig
  - Beslissingen bij problemen
- **Authenticatie:** Geheime URL
- **Voert NIET uit:** Mat/weging/spreker acties (alleen toezicht)

---

## Toernooi Niveau - Gastrollen (dag van toernooi)

> Kunnen NIET bij instellingen

### Mat
- **Taken:** Wedstrijden afhandelen, scores invoeren
- **Authenticatie:** Geheime URL
- **Toegang:** Alleen mat interface

### Weging
- **Taken:** Judoka's wegen, gewicht registreren
- **Authenticatie:** Geheime URL
- **Toegang:** Alleen weging interface

### Spreker
- **Taken:** Omroepen, volgende wedstrijden aankondigen
- **Authenticatie:** Geheime URL
- **Toegang:** Alleen spreker interface

### Deurscanner (Dojo)
- **Taken:** Toegang controleren bij ingang dojo
- **Authenticatie:** Geheime URL
- **Toegang:** Alleen scanner interface

---

## Uitnodigingen Kant

### Judoschool (Club)
- **Wie:** De uitgenodigde club/vereniging
- **Taken:**
  - Ontvangt links en wachtwoorden
  - Beheert eigen coaches
  - Betalingen (future plan)
- **Authenticatie:** Portal link per club

### Coach (Coachpage)
- **Wie:** Trainer van uitgenodigde club
- **Taken:**
  - Judoka's aanmelden/beheren
  - Weegkaarten bekijken/delen (pas beschikbaar na blokverdeling)
- **Authenticatie:** Gedeelde URL + 5-cijfer PIN
- **Beheer:** Judoschool regelt dit zelf (max 3 coaches per club)

### Wedstrijdcoach
- **Wie:** Coach die op de dag aanwezig is
- **Taken:** Judoka's begeleiden tijdens wedstrijden
- **Toegang:** Dojo betreden met coachkaart
- **Authenticatie:** Coachkaart (QR/barcode)

---

## Judoka's (Deelnemers)

- **Wie:** De wedstrijddeelnemers
- **Aangemeld door:** Coach via coachpage
- **Identificatie:** Weegkaart met QR code
- **Geen login:** Geen eigen account nodig

---

## Toegangsmatrix

| Functie | Admin | Organisator | Hoofdjury | Mat | Weging | Spreker | Dojo |
|---------|-------|-------------|-----------|-----|--------|---------|------|
| **Instellingen** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Noodplan** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Poules beheer** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Mat interface** | ✅ | 👁️ | 👁️ | ✅ | ❌ | ❌ | ❌ |
| **Weging interface** | ✅ | 👁️ | 👁️ | ❌ | ✅ | ❌ | ❌ |
| **Spreker interface** | ✅ | 👁️ | 👁️ | ❌ | ❌ | ✅ | ❌ |
| **Dojo scanner** | ✅ | 👁️ | 👁️ | ❌ | ❌ | ❌ | ✅ |

✅ = Volledige toegang | 👁️ = Alleen bekijken | ❌ = Geen toegang

---

## Notities

- **Admin** is altijd passe-partout (technische bypass)
- **Organisator** en **Hoofdjury** hebben dezelfde rechten, verschil is verantwoordelijkheid
- **Gastrollen** zijn tijdelijk en alleen actief op toernooidag
- **KYC** (Know Your Customer) alleen verplicht voor Organisator

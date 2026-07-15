---
title: URL-schema: auth, organisator, sitebeheerder en toernooibeheer
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# URL-schema: auth, organisator, sitebeheerder en toernooibeheer

> Onderdeel van [URL Structuur](../URL-STRUCTUUR.md).

## URL Schema

### 1. Homepage & Auth

Algemene pagina's zonder organisator context.

| URL | Doel | Auth |
|-----|------|------|
| `/` | Homepage | Geen |
| `/login` | Organisator login | Geen |
| `/registreren` | Nieuwe organisator | Geen |
| `/wachtwoord-vergeten` | Wachtwoord reset | Geen |
| `/wachtwoord-reset/{token}` | Nieuw wachtwoord instellen | Geen |

---

### 2. Organisator Beheer

**Auth: Organisator login vereist**

Alle routes onder `/{org}/...` (zonder `toernooi` erna) zijn organisator-niveau instellingen.

| URL | Doel |
|-----|------|
| `/{org}/dashboard` | Overzicht toernooien |
| `/{org}/clubs` | Club beheer (blijft bewaard tussen toernooien) |
| `/{org}/templates` | Toernooi templates |
| `/{org}/presets` | Gewichtsklassen presets |

**Voorbeeld:** `/judo-ceesveen/dashboard`

---

### 3. Sitebeheerder (Superadmin)

**Auth: Organisator login + sitebeheerder rol**

Alleen toegankelijk voor henkvu@gmail.com.

| URL | Doel |
|-----|------|
| `/admin` | Overzicht alle organisatoren en toernooien |

---

### 4. Toernooi Beheer

**Auth: Organisator login vereist**

> ⚠️ Let op: Het woord `toernooi` in de URL geeft aan dat login vereist is.

| URL | Doel |
|-----|------|
| `/{org}/toernooi/nieuw` | Nieuw toernooi aanmaken |
| `/{org}/toernooi/{toernooi}` | Toernooi startpagina (beheer) |
| `/{org}/toernooi/{toernooi}/edit` | Instellingen |
| `/{org}/toernooi/{toernooi}/judoka` | Judoka beheer |
| `/{org}/toernooi/{toernooi}/poule` | Poule beheer |
| `/{org}/toernooi/{toernooi}/club` | Club selectie voor dit toernooi |
| `/{org}/toernooi/{toernooi}/blok` | Blokken/tijdslots |
| `/{org}/toernooi/{toernooi}/weging` | Weging overzicht |
| `/{org}/toernooi/{toernooi}/mat` | Mat overzicht |
| `/{org}/toernooi/{toernooi}/noodplan` | Print/noodplan |
| `/{org}/toernooi/{toernooi}/upgrade` | Upgrade naar betaald plan |

**Voorbeeld:** `/judo-ceesveen/toernooi/open-2026/poule`

---


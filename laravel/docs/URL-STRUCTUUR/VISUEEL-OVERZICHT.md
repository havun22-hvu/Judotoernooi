---
title: Visueel overzicht van de URL-boom
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Visueel overzicht van de URL-boom

> Onderdeel van [URL Structuur](../URL-STRUCTUUR.md).

## Visueel Overzicht

```
/                                          ← Homepage
├── /login                                 ← Organisator login
├── /registreren                           ← Registratie
├── /admin                                 ← Sitebeheerder
│
├── /weegkaart/{token}                     ← QR: Weegkaart
├── /coach-kaart/{qrCode}                  ← QR: Coach kaart
│
└── /{org}/                                ← Organisator context
    │
    ├── dashboard                          ← [LOGIN] Dashboard
    ├── clubs                              ← [LOGIN] Clubs beheer
    ├── templates                          ← [LOGIN] Templates
    ├── presets                            ← [LOGIN] Presets
    │
    ├── toernooi/                          ← [LOGIN] Toernooi beheer
    │   ├── nieuw                          ← Nieuw toernooi
    │   └── {toernooi}/
    │       ├── (root)                     ← Toernooi startpagina
    │       ├── edit                       ← Instellingen
    │       ├── judoka                     ← Judoka's
    │       ├── poule                      ← Poules
    │       ├── club                       ← Club selectie
    │       ├── blok                       ← Blokken
    │       ├── weging                     ← Weging
    │       ├── mat                        ← Matten
    │       └── noodplan                   ← Print
    │
    └── {toernooi}/                        ← [PUBLIEK] Publieke pagina's
        │
        ├── (root)                         ← Publieke pagina (PWA)
        │
        ├── school/{code}/                 ← [PIN] Coach portal
        │   ├── judokas
        │   ├── weegkaarten
        │   └── afrekenen
        │
        └── toegang/{code}                 ← [DEVICE] Vrijwilligers (device binding, geen PIN)
            ├── weging/{toegang}
            ├── mat/{toegang}
            ├── jury/{toegang}
            ├── spreker/{toegang}
            └── dojo/{toegang}
```

---


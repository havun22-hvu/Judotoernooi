---
title: Weegkaart (judoka's eigen kaart)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Weegkaart (judoka's eigen kaart)

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

## Weegkaart (Judoka's eigen kaart)

**Route:** `/weegkaart/{qr_code}`
**View:** `resources/views/pages/weegkaart/show.blade.php`
**Controller:** `WeegkaartController@show`

### Inhoud Weegkaart
- **Header**: Toernooi naam + datum
- **Naam**: Groot en prominent (voor weegkamer)
- **Club**: Onder de naam
- **Classificatie**: Leeftijd, gewicht, band, geslacht
- **Blok info** (indien toegewezen):
  - Blok naam (bijv. "Blok 1")
  - Starttijd wedstrijden
  - Weegtijden (start - einde)
  - **Mat nummer** (zodra toegewezen)
- **QR code**: Voor scannen bij weging
- **Download/Delen**: Knoppen voor opslaan en delen

### Vereisten
1. Judoka moet in poule zitten → blok wordt getoond
2. Poule moet mat hebben → mat wordt getoond

**Belangrijk:** Weegkaarten zijn dynamisch en tonen altijd actuele info.

---


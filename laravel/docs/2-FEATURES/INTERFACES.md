# Interfaces per Rol

## Voorbereiding vs Wedstrijddag - Belangrijk!

### Blokken (LOCKED na voorbereiding)
- Blokindeling kan **NIET** meer wijzigen na voorbereiding
- Weegkaarten zijn al geprint/verstuurd
- Website info is al gepubliceerd
- **Readonly** op wedstrijddag

### Poules (LOCKED na voorbereiding)
- Poules tab = alleen voor **indeling**
- Pouleindeling kan **NIET** meer wijzigen na voorbereiding
- **Readonly** op wedstrijddag

### Wedstrijddag (KAN wijzigen)
- Start als **kopie** van de pouleindeling
- Kan wijzigen door:
  - Overpoulen (te weinig deelnemers)
  - Absentie (judoka komt niet opdagen)
- Poulestanden worden hier bijgehouden
- Dit is de **actieve werkplek** op de wedstrijddag

### Referentie (readonly)
- Hoofdjury moet poules/blokken kunnen **inzien** voor referentie
- Bijv. bij protesten, vragen van coaches
- Maar **niet bewerken** op wedstrijddag

---

## Overzicht Wedstrijddag

| Interface | Device | Gebruiker | Doel |
|-----------|--------|-----------|------|
| **Dojo Scanner** | Smartphone | Ingang | QR codes scannen bij binnenkomst |
| **Weging** | Smartphone/Tablet | Weegteam | Judoka's wegen en registreren |
| **Mat** | PC/Laptop/Tablet | Tafelmedewerker | Wedstrijden per mat beheren |
| **Spreker** | iPad/Tablet | Omroeper | Aankondigingen en oproepen |

## Jurytafel (Hoofdtafel)

De jurytafel heeft tijdens de **wedstrijddag** alleen nodig:

### Essentieel
- **Weeglijst** - Controle wie gewogen is
- **Wedstrijddag** - Overzicht lopende wedstrijden
- **Zaaloverzicht** - Alle matten in 1 beeld

### Bonus (indien nodig)
- **Matten** - Direct ingrijpen bij specifieke mat
- **Spreker** - Omroep overnemen

### Niet nodig op wedstrijddag
- ~~Poules~~ - Voorbereidingstab (al ingedeeld)
- ~~Blokken~~ - Voorbereidingstab (al gepland)
- ~~Judoka's~~ - Alleen voor wijzigingen

## Voorbereiding vs Wedstrijddag

| Tab | Voorbereiding | Wedstrijddag |
|-----|---------------|--------------|
| Judoka's | Beheren | - |
| Poules | Indelen | - |
| Blokken | Plannen | - |
| Weging | - | Actief |
| Wedstrijddag | - | Actief |
| Zaaloverzicht | - | Actief |
| Matten | - | Actief |
| Spreker | - | Actief |

## PWA Installatie

Elke interface kan als losse PWA geinstalleerd worden:

```
Dojo Scanner  → manifest-dojo.json    → Smartphone
Weging        → manifest-weging.json  → Smartphone/Tablet
Mat           → manifest-mat.json     → PC/Laptop/Tablet
Spreker       → manifest-spreker.json → iPad/Tablet
```

**Tip:** Installeer de PWA vanaf de juiste pagina (bijv. `/toernooi/5/dojo/scanner`), dan opent de app direct die interface.

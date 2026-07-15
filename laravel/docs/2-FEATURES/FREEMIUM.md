---
title: Freemium Model
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Freemium Model

> **Doel:** Organisatoren gratis laten uitproberen, betalen voor grotere toernooien.
> **Index-doc:** het business model, de gratis limieten en de betaalde staffels staan hieronder; de details staan in de deeldocs onder [`FREEMIUM/`](./FREEMIUM/).


---

## Business Model

JudoToernooi hanteert een freemium model:

| Aspect | Gratis | Betaald |
|--------|--------|---------|
| Judoka's | Max 50 | 51-500+ |
| Demo CSV import | Ja (30, 40 of 50 judoka's) | Niet nodig |
| Eigen CSV import | Ja, max 20 judoka's | Onbeperkt |
| Handmatig toevoegen | Max 20 (coach portal / handmatig) | Onbeperkt |
| Clubs | Max 2 actief | Onbeperkt |
| Presets | Max 1 | Onbeperkt |
| Eliminatie systeem | Nee (alleen poules/kruisfinale) | Volledig (dubbel eliminatie, IJF repechage) |
| Danpunten (JBN) | Nee | Volledig |
| Print/Noodplan | Nee | Volledig |
| Prijs | Gratis | Vanaf €20 |

---

## Gratis Tier Limieten

| Limiet | Waarde | Enforcement |
|--------|--------|-------------|
| Judoka's totaal | 50 | `Toernooi::canAddMoreJudokas()` |
| Eigen CSV import | Max 20 judoka's | `ImportController` |
| Handmatig toevoegen | Max 20 | `JudokaController` / `CoachPortalController` |
| Actieve clubs | 2 | `ClubController` |
| Presets | 1 | `GewichtsklassenPresetController` |
| Eliminatie systeem | Nee | `Toernooi::canUseEliminatie()` — optie verborgen in UI |
| Print/Noodplan | Nee | `CheckFreemiumPrint` middleware |

### Waarom deze limieten?

- **50 judoka's** = genoeg om een volledig toernooi te testen
- **Demo CSV** = direct werkende poules/schema's zonder zelf data in te voeren
- **Eigen CSV max 20** = testen of eigen Excel/CSV werkt, maar niet genoeg voor een echt toernooi
- **Handmatig max 20** = coach portal / handmatig invoeren testen
- **2 clubs** = Eigen club + 1 gastclub
- **1 preset** = Basis gewichtsklassen
- **Geen eliminatie** = geavanceerd systeem, stimuleert upgrade
- **Geen print** = stimuleert upgrade voor echte wedstrijddag

---

## Betaalde Staffels

| Staffel | Max Judoka's | Prijs |
|---------|--------------|-------|
| Klein | 100 | €20 |
| Medium | 150 | €30 |
| Groot | 200 | €40 |
| XL | 250 | €50 |
| XXL | 300+ | €60+ |

**Formule:** Basis €20 + €10 per extra 50 judoka's

```php
// FreemiumService::getStaffelPrijs()
$basis = 20;
$perExtra50 = 10;
$staffels = ceil(($maxJudokas - 50) / 50);
return $basis + (($staffels - 1) * $perExtra50);
```

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [DEMO-CSV-EN-TOERNOOI-TYPE.md](./FREEMIUM/DEMO-CSV-EN-TOERNOOI-TYPE.md) | Je werkt aan de demo-CSV import van het free tier, de kenmerken van demo-judoka's, of aan welke toernooi-typen zichtbaar zijn in de UI. |
| [UPGRADE-FLOW-EN-UI.md](./FREEMIUM/UPGRADE-FLOW-EN-UI.md) | Je volgt of wijzigt de upgrade-flow van gratis naar betaald, of raakt de freemium-banner, upgrade-pagina of print-blokkade. |
| [WIMPEL-EN-DATABASE.md](./FREEMIUM/WIMPEL-EN-DATABASE.md) | Je moet weten hoe het wimpel-abonnement wordt geactiveerd of verloopt, of welke kolommen in `toernooien` / `toernooi_betalingen` staan. |
| [IMPLEMENTATIE-EN-ROUTES.md](./FREEMIUM/IMPLEMENTATIE-EN-ROUTES.md) | Je zoekt `FreemiumService`- of `Toernooi`-methods, waar een limiet wordt afgedwongen, welke route wat doet, of hoe de upgrade-webhook werkt. |
| [TEST-ORGANISATOR-EN-TESTEN.md](./FREEMIUM/TEST-ORGANISATOR-EN-TESTEN.md) | Je wilt freemium lokaal of op staging testen, of de test-organisator gebruiken/aanmaken om limieten te omzeilen. |

## Relatie met BETALINGEN.md

Dit document gaat over **toernooi upgrades** (organisator betaalt aan JudoToernooi).

`BETALINGEN.md` gaat over **inschrijfgeld** (coach betaalt aan organisator).

| Aspect | Freemium (dit doc) | Inschrijfgeld |
|--------|-------------------|---------------|
| Wie betaalt | Organisator | Coach/judoschool |
| Aan wie | JudoToernooi | Organisator |
| Waarvoor | Meer capaciteit | Deelname judoka's |
| Mollie mode | Platform (altijd) | Connect of Platform |
| Webhook | `/mollie/webhook/toernooi` | `/mollie/webhook` |

# Echt-device test-checklist

> Playwright's Pixel7-emulatie forceert een eigen viewport en mist echte
> mobiele-device-issues (overflow, zoom, PWA-install, passkey, oriëntatie). Deze
> checklist is de **handmatige** sweep op een fysiek toestel — geen automatisering
> kan dit volledig vervangen. Zie [[csp-alpine-gotchas]] (passkey domeingebonden).

## Hoe te gebruiken

1. Kies een omgeving: **staging** (`staging.judotournament.org`) voor nieuwe features,
   **productie** voor passkey/biometrie (domeingebonden — staging-passkey werkt niet op prod).
2. Loop per scherm de punten af, vul pass/fail in. Noteer toestel + OS + browser + datum.
3. Een rode regel → issue aanmaken met screenshot, niet stilzwijgend doorgaan.

| Veld | Waarde |
|------|--------|
| Toestel / OS | bv. Huawei P10 / Android 9 |
| Browser | bv. Chrome 124 |
| Omgeving | staging / productie |
| Datum / tester | |

## Generiek (elk scherm)

- [ ] Geen horizontale scroll / overflow buiten de viewport
- [ ] Geen ongewenste zoom bij focus op invoervelden (font-size ≥ 16px op inputs)
- [ ] Tap-targets groot genoeg (knoppen niet te dicht op elkaar)
- [ ] Draaien portrait ↔ landscape breekt de layout niet
- [ ] Donkere/lichte modus van het toestel verstoort de kleuren niet

## Per scherm

### Organisator-dashboard
- [ ] Tabbalk past binnen de header (was breder dan header op mobiel → `overflow-x-auto`)
- [ ] Gear-/instellingen-icon + taalvlag staan netjes naast elkaar
- [ ] Backup-toast overlapt de PWA-installknop niet (bekend z-index-gat:
      installBanner `publiek/index` `fixed bottom-0 z-50` vs toast in `layouts/app`)

### Publiek (live matten)
- [ ] LIVE/OFFLINE-indicator klopt met de echte verbindingsstatus
- [ ] PWA-installknop zichtbaar én klikbaar (niet onder een melding)
- [ ] Matten-overzicht ververst live (realtime), geen handmatige refresh nodig

### Mat-interface (PWA, device-bound)
- [ ] Verbindingsbol wordt **groen** bij verbinding (rood bij verlies)
- [ ] Bracket/poule-lijst scrollt en is sleepbaar (Sortable) zonder hangen
- [ ] Dubbeltik zet wedstrijdstatus (groen/geel/blauw) correct
- [ ] Menu: data verversen / scorebord openen / help werken

### Weging (PWA)
- [ ] Numeriek toetsenbord verschijnt bij gewicht-invoer
- [ ] Geldige weging slaagt; <15kg geweigerd met duidelijke melding

### Spreker (PWA)
- [ ] Realtime updates komen binnen (poule klaar → herlaadt)
- [ ] Lange namen/clubs breken de layout niet

### Scorebord-LCD (TV)
- [ ] Bereikbaar via beide URLs: **Kort** (`havun.nl/tv/{code}`) + **Volledig**
- [ ] Timer, scores, shido's, osaekomi leesbaar op afstand (clamp-fontgroottes)
- [ ] Omgeving-badge (rood "STAGING"/"LOCAL") zichtbaar buiten productie, niets op prod
- [ ] "GEEN VERBINDING"-overlay verschijnt bij verbindingsverlies + herstelt

### Coach-portal (clubs, PIN)
- [ ] PIN-invoer werkt op mobiel; QR-koppeling scant

### Biometrie / passkey (ALLEEN productie)
- [ ] Passkey-registratie + login werkt op het toestel (domeingebonden — niet op staging)
- [ ] Magic link + QR als fallback werken

## Optioneel: geautomatiseerd op echte devices (BrowserStack)

Een stub-config staat klaar in `playwright.device.config.ts`. Niet geactiveerd
(geen credentials). Activeren: zet `BROWSERSTACK_USERNAME`/`BROWSERSTACK_ACCESS_KEY`
en draai tegen staging — zie de config-comments. Vervangt deze handmatige sweep
niet volledig (passkey/biometrie blijven device+domein-gebonden), maar dekt
layout/overflow op echte toestellen breed af.

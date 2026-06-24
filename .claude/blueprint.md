> **Blueprint** — JUDOTOERNOOI | Publiek/mobiel scorebord (portrait) | Herzien door Claude op echte code, 25-06-2026
> (Gemini-versie was speculatief: gokte Echo/`ScoreboardUpdateEvent`/`data.redScore` + privé-channel + nieuwe backend — alles onjuist. Dit plan is op de werkelijke code.)

## Wat er al is (geen werk nodig)
- `scoreboard-display.{toernooiId}.{matId}` channel is **publiek** (`channels.php:54` → `return true`).
- De LCD-view `scoreboard-live.blade.php` is **publiek bereikbaar** (`{org}/{toernooi}/mat/scoreboard-live/{mat}` + `/tv/{code}`), geen auth.
- Live-relay werkt via **raw Pusher** (`new Pusher(reverbKey,{wsHost,wsPort,...})`), bind `scoreboard.event` → `handleEvent(payload.data||payload)`. Plain vanilla JS onder `@nonce` (geen Alpine).

## De enige echte gap
De LCD-view is **landscape-only**. Op een telefoon in **portrait** is dat onbruikbaar. De Android-app heeft portrait+landscape; de LCD blijft bewust landscape-only. Nodig: een **publieke mobiele view die portrait én landscape doet**, met dezelfde live-data.

## Aanpak (DRY + LCD veilig)
1. **Engine-partial** `mat/partials/_scoreboard-engine.blade.php`: verplaats de herbruikbare JS uit `scoreboard-live` (Pusher-setup, `handleEvent`, `loadMatch`, timer/osaekomi-tick, scores, awasete/ippon/winner, disconnect-overlay, sound). Werkt op element-IDs + erft blade-vars (`$reverbKey/$reverbHost/$reverbPort/$reverbScheme`, `$toernooi`, `$matId`, `$blauwRechts`). Houdt `@nonce`.
2. **LCD refactoren** (RISICO — productie-kritisch): `scoreboard-live.blade.php` includeert de partial i.p.v. inline JS. Element-IDs **ongewijzigd** → gedrag identiek. **Verificatie:** bestaande PHPUnit scoreboard-tests + `visual.auth.spec.ts` (dekt de LCD-baseline) moeten ongewijzigd groen.
3. **Mobiele view** `mat/scoreboard-mobile.blade.php`: portrait-first responsive HTML met **dezelfde element-IDs** (wit/blauw-naam/club, *-yuko/wazaari/ippon/shido, timer-display, osaekomi, winner-overlay, disconnect-overlay) zodat de engine 1-op-1 werkt. Portrait: wit boven / timer midden / blauw onder. Landscape: `@media(orientation:landscape)` → LCD-achtige rij-layout. Includeert de partial.
4. **Route + controller**: publieke `GET {org}/{toernooi}/mat/scoreboard-mobiel/{mat}` → nieuwe `MatController::scoreboardMobile()` die dezelfde data doorgeeft als `scoreboardLive()` (hergebruik de bestaande data-opbouw, geen auth). Toegang: knop/QR naast de bestaande "Kort/Volledig" device-toegang-links.
5. **Tests**: route 200 + publiek (geen auth) + view rendert; `scoreboard-mobile` visual-regression baseline (portrait+landscape viewport). LCD-baseline ongewijzigd.

## Noodplan (lokaal-failover)
NIET via `.env`-flag (Henk raakt `.env` niet aan). De bestaande lokale-server/standby-logica bepaalt al de modus — de publieke mobiele view valt onder dezelfde Reverb-afhankelijkheid als de LCD; bij lokaal-only failover is er geen extra actie nodig (publiek heeft sowieso geen internet-Reverb). Documenteren in `PUBLIEK-SCOREBORD.md`, geen aparte kill-switch tenzij gewenst.

## Risico's / beslispunten
- **Stap 2 raakt de live LCD.** Mitigatie: mechanische verplaatsing (zelfde code/IDs) + visual-regression + scoreboard-tests vóór commit. Als je dit risico niet wilt: alternatief = mobiele view met een **eigen kopie** van de engine (LCD 100% ongemoeid, prijs = JS-duplicatie). Aanbeveling: gedeelde partial mét verificatie.
- `els`-cache + var-scope moeten in de partial kloppen (include erft parent-scope).
- Mobiele HTML moet de **volledige ID-set** repliceren, anders null-errors in `handleEvent`.

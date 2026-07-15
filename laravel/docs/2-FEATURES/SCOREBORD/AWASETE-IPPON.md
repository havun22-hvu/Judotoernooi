---
title: Awasete-ippon waarschuwing
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Awasete-ippon waarschuwing

> Onderdeel van [Scorebord-app](../SCOREBORD-APP.md).

## Awasete-ippon waarschuwing (2e waza-ari tijdens osaekomi)

> **Status:** live (juni 2026). App `dda66c4`, backend+display `84a79367`.
> **Doel:** andere systemen scoren bij een 2e waza-ari automatisch ippon — wij niet.
> De app **waarschuwt de scheidsrechter via het LCD-display**; de scheids beslist, de
> tafelofficial tekent. App scoort NOOIT zelf.

### Trigger
Osaekomi bereikt de **W-drempel** (default 10s) terwijl de houdende judoka **al ≥1 waza-ari**
heeft (`scores[side].wazaari >= 1`). Een eerste waza-ari triggert dit niet.

### Gedrag
| Kant | Wat | Prioriteit |
|------|-----|-----------|
| **Display (LCD) — scheids** | Knipperende rode balk bovenin `⚠ 2e WAZA-ARI — IPPON? ⚠` + lopende houdtijd, **tot toketa** + **eenmalig** instelbaar geluid. Scores blijven zichtbaar. | **Hoofd** |
| **Bediening (app) — tafel** | Bestaande **eenmalige** osaekomi-toon + knipperende osaekomi-tijd. Geen luid herhalend alarm. | Bijzaak |

De balk gaat weer weg (`osaekomi.warning active:false`) bij toketa, overname, mate,
`timer.reset` en `match.end` — dubbele dekking via ref-gestuurde exit + display-clear.
Bij overname worden `osaekomiAlertedRef`/`osaekomiLiveIndexRef` gereset zodat de nieuwe
houder zijn eigen waarschuwing krijgt.

### LCD-geluid (instelbaar, per apparaat)
Klein paneel op de LCD-pagina (tandwiel), opgeslagen in **localStorage** (LCD is publiek, geen login):
- **Aan/uit**, **volume** (0-100%), **keuze** piep / gong / sirene (WebAudio `OscillatorNode`, geen assets) + testknop.
- **Autoplay-policy:** browsers blokkeren geluid tot een user-gesture. De "🔊 Geluid aan"-knop
  `resume()`t de `AudioContext`. Tot die klik: alleen de visuele balk, geen geluid.

> Volledige spec: `JudoScoreBoard/.claude/context.md` → "Awasete-ippon waarschuwing" + plan in `JudoScoreBoard/.claude/plan-awasete-waarschuwing.md`.

---


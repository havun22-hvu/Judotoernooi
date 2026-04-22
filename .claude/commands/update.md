---
title: Update Command - Tussentijdse MD Check
type: claude
scope: judotoernooi
last_check: 2026-04-22
---

# Update Command - Tussentijdse MD Check

> **Gebruik:** Tijdens langere sessies of na significante wijzigingen

## Stap 1: Check wat er besproken is

Vraag jezelf:
- Wat hebben we deze sessie besproken/opgelost?
- Is er nieuwe kennis die opgeslagen moet worden?
- Zijn er beslissingen genomen die gedocumenteerd moeten worden?

## Stap 2: Controleer de MD file hiërarchie

### Project-specifiek (hoort IN het project):
```
{project}/CLAUDE.md           ← Regels, korte context (max 60-80 regels)
{project}/.claude/context.md  ← Features, database, hoe dingen werken
{project}/.claude/rules.md    ← Security regels
```

### Gedeeld (hoort in HavunCore):
```
HavunCore/docs/kb/patterns/   ← Herbruikbare code patterns
HavunCore/docs/kb/runbooks/   ← How-to procedures
HavunCore/docs/kb/reference/  ← API specs, server info
HavunCore/docs/kb/decisions/  ← Architectuur beslissingen
HavunCore/.claude/context.md  ← Credentials, server info
```

## Stap 3: Voer updates door

### Checklist:
- [ ] **Nieuwe features/functionaliteit?** → Update project context.md
- [ ] **Nieuw herbruikbaar pattern?** → Maak file in HavunCore/docs/kb/patterns/
- [ ] **Probleem opgelost dat vaker voorkomt?** → Maak runbook in HavunCore
- [ ] **Architectuur beslissing genomen?** → Maak ADR in HavunCore/docs/kb/decisions/
- [ ] **Credentials/API keys veranderd?** → Update HavunCore/.claude/context.md
- [ ] **Project regels veranderd?** → Update project CLAUDE.md

### Waar hoort wat?

| Type informatie | Locatie |
|-----------------|---------|
| "Hoe werkt feature X in dit project" | `{project}/.claude/context.md` |
| "Hoe implementeer je email verificatie" | `HavunCore/docs/kb/patterns/` |
| "Hoe deploy ik naar server" | `HavunCore/docs/kb/runbooks/` |
| "Waarom gebruiken we X ipv Y" | `HavunCore/docs/kb/decisions/` |
| "Server IP, API keys" | `HavunCore/.claude/context.md` |

## Stap 4: Bevestig aan gebruiker

```
📝 MD files bijgewerkt:
  - [bestand]: [wat gewijzigd]
  - [bestand]: [wat gewijzigd]

✓ Hiërarchie gecontroleerd - alles staat op de juiste plek
```

OF als er niks te updaten is:

```
✓ Geen MD updates nodig - alles is actueel
```

## NIET DOEN

❌ Informatie dubbel opslaan (in project EN HavunCore)
❌ Project-specifieke info in HavunCore zetten
❌ Gedeelde patterns alleen in één project houden
❌ Vergeten te documenteren "omdat het toch duidelijk is"

# End Session Command

> **VERPLICHT** bij elke sessie-afsluiting - laat het project netjes achter!

## 1. MD Bestanden Netjes Achterlaten (KRITIEK!)

### Controleer en update:

```
CLAUDE.md                    ← Zijn er nieuwe regels/restricties?
.claude/context.md           ← Is er nieuwe project kennis?
```

### Vraag jezelf:
- [ ] Wat hebben we besproken dat NIET gedocumenteerd is?
- [ ] Zijn er beslissingen genomen die vastgelegd moeten worden?
- [ ] Heeft de gebruiker iets uitgelegd dat opgeslagen moet worden?
- [ ] Zijn er nieuwe patterns/oplossingen die herbruikbaar zijn?

### Waar opslaan?

| Nieuwe kennis | Locatie |
|---------------|---------|
| Project-specifiek | `.claude/context.md` |
| Herbruikbaar pattern | `D:\GitHub\HavunCore\docs\kb\patterns\` |
| How-to procedure | `D:\GitHub\HavunCore\docs\kb\runbooks\` |
| Architectuur beslissing | `D:\GitHub\HavunCore\docs\kb\decisions\` |

## 2. Maak een Handover voor Volgende Sessie

Voeg toe aan `.claude/context.md` of maak `.claude/handover.md`:

```markdown
## Laatste Sessie: [DATUM]

### Wat is gedaan:
- [Taak 1]
- [Taak 2]

### Openstaande items:
- [ ] [Nog te doen 1]
- [ ] [Nog te doen 2]

### Belangrijke context voor volgende keer:
- [Relevante info die de volgende Claude moet weten]
- [Beslissingen die genomen zijn en waarom]

### Bekende issues/bugs:
- [Issue 1]
```

## 3. Git Commit & Push

```bash
git add .
git commit -m "docs: Session handover [datum] + [korte beschrijving]"
git push origin master
```

## 4. Deploy naar Server (indien nodig)

```bash
ssh root@188.245.159.115
cd [project path]  # Zie HavunCore/.claude/context.md voor paden
git pull
php artisan config:clear && php artisan cache:clear
```

## 5. Branch Cleanup

```bash
git branch --merged | grep -v master | xargs git branch -d
```

## 6. Bevestig aan Gebruiker

```
📋 Sessie Samenvatting:
  - [Wat gedaan]

📝 Gedocumenteerd:
  - [Welke MD files bijgewerkt]

⏳ Openstaand:
  - [Nog te doen]

✅ Handover gemaakt voor volgende sessie
✅ Git gepusht

Sessie afgerond. Typ 'exit' of Ctrl+D om te sluiten.
```

## NIET DOEN BIJ AFSLUITEN

❌ Afsluiten zonder MD files te checken
❌ Kennis "in je hoofd houden" - de volgende Claude weet het niet!
❌ Geen handover maken bij openstaande items
❌ Pushen zonder duidelijke commit message

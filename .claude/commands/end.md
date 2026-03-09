# End Session Command

> **VERPLICHT** bij elke sessie-afsluiting - laat het project netjes achter!

## 1. Review Smallwork.md (EERST!)

Lees `.claude/smallwork.md` en check elke entry:

```
Voor elke fix in smallwork.md:
  ├── Moet dit naar permanente docs?
  │     ├── Feature/functionaliteit → SPEC.md of FEATURES.md
  │     ├── Styling → STYLING.md
  │     ├── Business rule → relevante doc
  │     └── Technisch/eenmalig → blijft in smallwork
  │
  └── Verplaats indien nodig en vink af
```

## 2. MD Bestanden Netjes Achterlaten (KRITIEK!)

### Controleer en update:

```
CLAUDE.md                    ← Zijn er nieuwe regels/restricties?
.claude/context.md           ← Is er nieuwe project kennis?
.claude/smallwork.md         ← Is alles afgehandeld?
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

## 3. Maak een Handover voor Volgende Sessie

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

## 4. Git Commit & Push

```bash
git add .
git commit -m "docs: Session handover [datum] + [korte beschrijving]"
git push origin master
```

## 5. Deploy naar Server (indien nodig)

```bash
ssh root@188.245.159.115
cd [project path]  # Zie HavunCore/.claude/context.md voor paden
git pull
php artisan config:clear && php artisan cache:clear
```

## 6. Branch Cleanup

```bash
git branch --merged | grep -v master | xargs git branch -d
```

## 7. Urenregistratie + Bevestiging

Geef een beknopt overzicht voor de urenregistratie. Dit is voor de belastingdienst: alleen aannemelijk maken dat er gewerkt is.
**GEEN commit details of technische beschrijvingen.** Alleen projectnaam + globaal onderwerp (max 3 woorden).

```
[DD-MM-YYYY]:
- [Project]: [globaal onderwerp, max 3 woorden]

Handover gemaakt, git gepusht.
Openstaand: [korte lijst of "geen"]
```

## NIET DOEN BIJ AFSLUITEN

❌ Afsluiten zonder smallwork.md te reviewen
❌ Afsluiten zonder MD files te checken
❌ Kennis "in je hoofd houden" - de volgende Claude weet het niet!
❌ Geen handover maken bij openstaande items
❌ Pushen zonder duidelijke commit message

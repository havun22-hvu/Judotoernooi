# Smallwork Log

> Kleine technische fixes die niet in permanente docs hoeven.
>
> **Wat hoort hier:**
> - Typos in code (variabele namen, comments)
> - Bug fixes (iets werkt niet zoals spec zegt)
> - Performance optimalisaties
> - Technische refactoring
> - Dependency updates
>
> **Wat hoort hier NIET:**
> - Features → SPEC.md of FEATURES.md
> - Styling/design → STYLING.md
> - Prijzen/teksten → relevante doc
> - User flows → relevante doc

---

## Sessie: 5 januari 2026

### Fix: Leeftijdsklasse string conversie
- **Type:** Bug fix
- **Wat:** `B-pupillen` werd niet correct omgezet naar `b_pupillen` in KO settings
- **Waarom:** `str_replace(" -", "_")` verving alleen " -" (spatie+hyphen), niet "-" alleen
- **Bestanden:** `resources/views/pages/toernooi/edit.blade.php:316-318`
- **Oplossing:** `preg_replace('/[\s\-]+/', '_', $lkKey)` voor alle spaties en hyphens
- **Naar permanente docs?** ☑ Nee - technische bugfix

### Fix: Gespiegelde slot logica verwijderd uit backend
- **Type:** Refactor
- **Wat:** `$gespiegeld` parameter en onderste helft logica verwijderd
- **Waarom:** Slots moeten ALTIJD van boven naar beneden genummerd zijn, zonder spiegeling
- **Bestanden:** `app/Services/EliminatieService.php`
- **Naar permanente docs?** ☑ Al gedaan → `docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md`

---

<!--
TEMPLATE voor nieuwe entry:

### Fix: [korte titel]
- **Type:** Bug fix / Performance / Refactor / Typo / Update
- **Wat:** [wat aangepast]
- **Waarom:** [reden]
- **Bestanden:** [welke files gewijzigd]
- **Naar permanente docs?** ☐ Ja → [welke doc] / ☑ Nee

-->

# Mutation testing (Infection)

> Coverage-% zegt of code wordt *uitgevoerd*, niet of je tests een fout zouden
> *vangen*. Infection muteert de broncode (bv. `>`→`>=`, `++`→`--`, `&&`→`||`) en
> draait per mutant alléén de dekkende tests. Overleeft een mutant, dan merkt geen
> enkele test de wijziging → loze assert. Dit meet de échte kwaliteit van de suite.

## Draaien

```bash
cd laravel
composer infection
```

Dat doet twee stappen (zie `composer.json` scripts):
1. `composer infection:coverage` — genereert PHPUnit-coverage (pcov) via de
   toegewijde config `tests/infection/phpunit.xml` naar `tests/infection-coverage/`.
2. `infection --skip-initial-tests --coverage=… --filter=…` — muteert de kern-services
   en hergebruikt die coverage.

Rapporten landen in `tests/infection.html` (browsen), `tests/infection.log`
(alle overlevende mutanten met diff) en `tests/infection-summary.log`. Alle drie
gitignored, net als `tests/infection-coverage/` en `tests/.infection/`.

## Scope

Bewust beperkt tot de bug-gevoelige kern (anders draait Infection uren):

- `app/Services/CategorieClassifier.php`
- `app/Services/EliminatieService.php`
- `app/Services/WegingService.php`
- `app/Services/PouleIndelingService.php`

Uitbreiden = `--filter` in het `infection`-script aanpassen. De dekkende tests
staan in `tests/Unit/Services` (de enige testsuite in `tests/infection/phpunit.xml`).

## Baseline (22-06-2026)

| Metric | Waarde |
|--------|--------|
| Mutaties gegenereerd | 1105 |
| Gedood | 464 |
| **Overleefden (covered, niet gepakt)** | **441** |
| Niet gedekt | 86 |
| Timeouts | 16 (+98 "meer tijd nodig") |
| **MSI (Mutation Score Indicator)** | **47%** |
| Mutation Code Coverage | 91% |
| Covered Code MSI | 52% |

**De kernbevinding:** 91% coverage, maar MSI 47%. De tests ráken de code wél, maar
vangen bijna de helft van de logica-wijzigingen niet. Voorbeeld (overleefd):

```php
// CategorieClassifier.php:56 — ++ → -- overleeft: geen test controleert de
// uiteindelijke sorteervolgorde-waarde, alleen dát er gesorteerd is.
-            $sortCategorie++;
+            $sortCategorie--;
```

Overlevende mutanten clusteren in (meeste eerst): PouleIndelingService,
EliminatieService, CategorieClassifier, WegingService. De PouleIndeling-timeouts
komen grotendeels door de externe Python-solver (`DynamischeIndelingService`,
exec) — die drukt de MSI én de looptijd; te overwegen die uit `--filter` te halen
voor een snellere, zuiverdere meting van de pure PHP-logica.

> Doel-MSI op deze kern: **richtlijn ≥70%** (geen harde CI-gate vooralsnog). Het
> dichten van overlevende mutanten = aparte vervolgactie, niet deze opzet.

## Windows-bijzonderheid (waarom de twee-staps-flow)

Infection's standaard "Initial Tests Run" faalt op deze Windows-dev-box: de
gespawnde PHPUnit (Symfony Process, gepipede stdio) stopt direct na de header met
exit 1, terwijl exact hetzelfde commando in een gewone shell 813 tests groen
draait. Daarom genereren we de coverage met een **gewone** `phpunit`-aanroep
(stap 1) en draait Infection met `--skip-initial-tests` (stap 2). De per-mutant
testruns onder Infection werken wél (klein/snel). In CI/Linux is de standaardflow
prima; de twee-staps-vorm is deterministisch en cross-platform, dus hier de default.

De toegewijde `tests/infection/phpunit.xml` heeft één niet-overlappende testsuite
(`tests/Unit/Services`) — een subdir-suite die de root-`Unit`-suite overlapt geeft
in PHPUnit 11 "file already added to another testsuite" → geen tests → exit 1.

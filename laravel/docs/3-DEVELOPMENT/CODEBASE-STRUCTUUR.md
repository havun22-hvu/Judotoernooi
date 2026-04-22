---
title: Codebase-structuur
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# Codebase-structuur

Overzicht van de volledige projectstructuur (workspace + Laravel app). Bijgewerkt na codebase-scan.

## 1. Root (JudoToernooi)

| Item | Doel |
|------|------|
| **laravel/** | Laravel 11 app – hoofdapplicatie |
| **.claude/** | Claude context: CLAUDE.md (root), context.md, handover.md, deploy.md, smallwork.md, commands/, settings.json |
| **.github/** | CI: workflows/ci.yml |
| **legacy-gas/** | Legacy scripts (WebApp, send_weegkaart_mawin.js, Config.js, etc.) |
| **offline/** | Offline/noodplan: README, build-offline-laravel.sh, _tools/, build/, php/ |
| **Scripts/** | o.a. python/poule_solver_experiment.py |
| **README.md** | Projectintro, CI, quick start |
| **CLAUDE.md** | AI-instructies, docs-first, SaaS-regels, sync-server, KB-refs |
| **SECURITY.md** | Security-informatie |

## 2. Laravel-app (`laravel/`)

### app/

| Map | Inhoud |
|-----|--------|
| **Console/Commands/** | OfflineExport, HerberekePouleStatistieken, TestDynamischeIndeling, ValidateProductionCommand, MilestoneBackup, WedstrijddagBackup, DiagnoseToernooi, GenereerBlokVerdeling, CheckTranslations, ResetStaging |
| **Contracts/** | PaymentProviderInterface |
| **DTOs/** | PaymentResult |
| **Enums/** | Band, Leeftijdsklasse, Geslacht, AanwezigheidsStatus |
| **Events/** | NewChatMessage, MatUpdate |
| **Exceptions/** | JudoToernooiException, ImportException, MollieException, ExternalServiceException |
| **Exports/** | PouleExport, PouleBlokSheet, WimpelExport |
| **Helpers/** | BandHelper |
| **Http/Controllers/** | Toernooi, Judoka, Poule, Blok, Mat, Weging, Wedstrijddag, Club, OrganisatorAuth, Admin, Noodplan, CoachKaart, Wimpel, Mollie, Stripe, Chat, LocalSync, DeviceToegang, Health, Legal, Sitemap, …; **Auth/**: PinAuth, Passkey; **Api/**: SyncApi, ToernooiApi |
| **Http/Middleware/** | SecurityHeaders, SetLocale, OfflineMode, CheckToernooiRol, CheckRolSessie, CheckDeviceBinding, CheckFreemiumPrint, LocalSyncAuth |
| **Http/Requests/** | ToernooiRequest, JudokaStore/Update, ClubRequest, WegingRequest, WedstrijdUitslagRequest, StamJudokaRequest |
| **Jobs/** | ImportJudokasJob |
| **Mail/** | FactuurMail, AutoFixProposalMail, CorrectieVerzoekMail, ClubUitnodigingMail |
| **Models/** | Toernooi, Judoka, Poule, Wedstrijd, Blok, Mat, Club, Organisator, Coach, Weging, Wimpel*, ToernooiTemplate, ToernooiBetaling, Betaling, CoachKaart, CoachCheckin, ChatMessage, ActivityLog, SyncQueueItem, SyncStatus, AuthDevice, DeviceToegang, StamJudoka, Vrijwilliger, EmailLog, AutofixProposal, QrLoginToken, ClubUitnodiging, …; **Concerns/**: HasMolliePayments, HasPortaalModus, HasCategorieBepaling |
| **Observers/** | SyncQueueObserver |
| **Providers/** | AppServiceProvider, WebAuthnServiceProvider |
| **Services/** | ToernooiService, ImportService, PouleIndelingService, WegingService, EliminatieService, WedstrijdSchemaService, MollieService, FactuurService, WimpelService, BackupService, OfflineExportService, OfflinePackageBuilder, LocalSyncService, ErrorNotificationService, AutoFixService, FreemiumService, ActivityLogger, StambestandService, BracketLayoutService, DynamischeIndelingService, CategorieClassifier, InternetMonitorService, BlokMatVerdelingService, VariabeleBlokVerdelingService, PaymentProviderFactory; **Payments/**: MolliePaymentProvider, StripePaymentProvider; **BlokVerdeling/**: BlokScoreCalculator, BlokPlaatsingsHelper, BlokCapaciteitHelper, BlokVerdelingConstants, CategorieHelper |
| **Support/** | Result, CircuitBreaker |
| **WebAuthn/** | DatabaseChallengeRepository |

### config/

app, auth, session, broadcasting, reverb, services, webauthn, autofix, factuur, toernooi, local-server, gewichtsklassen.

### routes/

- **web.php** – Enige routebestand (web + API-achtige routes: ping, auth, sync, webhooks, organisator-scoped, toernooi, admin, coach-kaart, legal, sitemap).
- **channels.php** – Broadcasting.

### database/

- **migrations/** – Toernooien, judokas, poules, wedstrijden, blokken, wegingen, organisators, coaches, mollie/stripe/freemium, sync, webauthn, wimpel, stam_judokas, etc.
- **factories/** – Toernooi, Judoka, Club, Poule, Organisator, StamJudoka, Mat, Blok, Wedstrijd.

### resources/

| Map | Inhoud |
|-----|--------|
| **views/** | **layouts/**: app, print. **pages/**: admin, blok, club, coach, coach-kaart, dojo, help, judoka, mat, noodplan, offline, publiek, poule, resultaten, toernooi, weging, wedstrijddag, betaling, weegkaart, spreker. **organisator/**: auth, clubs, stambestand, wimpel. **emails/**, **pdf/**, **legal/**, **toernooi/**, **errors/** (403, 404, 419, 500, layout, vrijwilliger). **components/**: freemium-banner, internet-indicator, legal-layout, location-autocomplete, scoreboard, seo. **partials/**: chat-widget, chat-widget-hoofdjury, coach-locale-switcher, flag-icon, mat-updates-listener, pwa-mobile. |
| **js/** | app.js (Alpine.js + @alpinejs/collapse). |
| **css/** | app.css (Tailwind). |

### public/

index.php, robots.txt, sw.js, offline.html, manifest.json, manifest-dojo.json, manifest-spreker.json, manifest-mat.json, manifest-weging.json, build/ (Vite).

### tests/

TestCase, CreatesApplication; **Unit/**: JudokaTest, OrganisatorTest, WedstrijdTest, ToernooiFreemiumTest, WimpelServiceTest, CategorieClassifierTest, FreemiumServiceTest, EliminatieServiceTest, ImportServiceTest, ResultTest, CircuitBreakerTest; **Feature/**: HealthCheckTest, SecurityHeadersTest, CheckFreemiumPrintTest, ToernooiWimpelAboTest.

### Overig laravel-root

bootstrap/app.php (routing, rate limiters, schedule, middleware, exceptions), bootstrap/providers.php; lang/ (nl, en); phpunit.xml, phpstan.neon, .env.example, composer.json, CHANGELOG.md, README.md.

## 3. Documentatie

- **Hub**: laravel/docs/README.md.
- **Docs-map**: 1-GETTING-STARTED, 2-FEATURES (incl. ELIMINATIE/), 3-DEVELOPMENT, 4-PLANNING, 5-REGLEMENT, 6-INTERNAL, postmortem/; URL-STRUCTUUR.md in docs-root.
- **Overig**: CLAUDE.md, README.md, SECURITY.md (root); .claude/*; laravel/README.md, CHANGELOG.md; offline/README.md, offline/php/README.md.

## 4. Entry points

- **Routes**: laravel/routes/web.php (enige bestand in withRouting in bootstrap/app.php).
- **Bootstrap**: laravel/bootstrap/app.php, providers via bootstrap/providers.php.
- **Web**: laravel/public/index.php.

## 5. Frontend / build

- **package.json** (laravel/): Vite, Alpine.js, @alpinejs/collapse, Tailwind, laravel-vite-plugin.
- **vite.config.js**: Laravel plugin, entry resources/css/app.css + resources/js/app.js.
- **tailwind.config.js**: content = resources/**/*.blade.php, resources/**/*.js.
- Geen Vue/React; Blade + Alpine + Tailwind. Zie INTERFACES.md voor PWA-manifests.

## 6. Externe referenties

- **HavunCore** (D:\GitHub\HavunCore): docs/kb/ (runbooks, patterns), .claude/context.md, error notifications (STABILITY.md, ErrorNotificationService).
- **GitHub**: https://github.com/havun22-hvu/judotoernooi.
- Geen symlinks of gedeelde code in-repo.

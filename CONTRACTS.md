# CONTRACTS — JudoToernooi

> **Onveranderlijke regels van dit project.** Niemand mag deze regels overtreden — ook AI niet. Bij elke wijziging eerst raadplegen. Wijzigen mag alleen na schriftelijk akkoord van eigenaar.

## Wat is een contract?

Een contract is een gedragsregel die los staat van de implementatie. Code mag refactoren, tests mogen wijzigen — externe gedrag in dit document mag NIET wijzigen zonder bewuste beslissing.

Bij twijfel: STOP, raadpleeg eigenaar (CLAUDE.md regel 6, runbook `test-repair-anti-pattern.md` in HavunCore).

---

## C-01: Multi-tenant isolatie — organisatoren zien nooit elkaars data

**Regel:** Elke query die toernooi-, club-, judoka-, of poule-data ophaalt MOET filteren op `organisator_id` van de geauthenticeerde organisator. Een organisator mag NOOIT data van een andere organisator zien — ook niet via direct gemanipuleerde URL-parameters of API-calls.

**Waarom:** SaaS-vertrouwen + AVG. Bij een lek = verlies van alle organisator-klanten + meldplicht.

**Bewijs:** `auth('organisator')->user()->organisator_id` filter, policies, global Eloquent scopes, `tests/Feature/MultiTenantIsolationTest.php`.

---

## C-02: Wedstrijdresultaten zijn onomkeerbaar na toernooi-afsluiting

**Regel:** Een toernooi met `state = 'afgesloten'` heeft bevroren wedstrijduitslagen. Geen organisator mag scores wijzigen na afsluiting — alleen sitebeheerder via `/admin` en alleen met audit-log entry. De afsluiting zelf is omkeerbaar (terug naar 'wedstrijddag') maar dat reset geen scores.

**Waarom:** Officiële uitslagen. Klachten van judoka's/clubs over gewijzigde scores ondermijnen het hele platform.

**Bewijs:** Toernooi-state-machine in `app/Models/Toernooi.php`, policies, audit-log.

---

## C-03: Magic-link auth heeft TTL van max 60 minuten

**Regel:** E-mail magic links voor login/registratie zijn maximaal 60 minuten geldig. Eenmaal gebruikt: meteen ingetrokken. Bij verloop: nieuwe link aanvragen, oude link dood.

**Waarom:** E-mail accounts kunnen gecompromitteerd zijn. Korte TTL beperkt blast-radius bij interceptie.

**Bewijs:** `MagicLinkService`, `passwords.expire` config, `tests/Feature/Auth/MagicLinkTest.php`.

---

## C-04: Stripe Connect betalingen produceren altijd een Invoice

**Regel:** Een succesvolle Stripe Connect-betaling van een organisator (toernooi-licentie) MOET binnen dezelfde transactie een `Invoice` record aanmaken én een sync naar HavunAdmin in de queue zetten. Geen wees-payments zonder factuur.

**Waarom:** Wettelijke verplichting boekhouding. Klant moet altijd factuur kunnen ophalen.

**Bewijs:** `StripeWebhookController`, `InvoiceService`, `SyncInvoiceJob`.

---

## C-05: Poule-indeling respecteert wettelijke gewichtsregels

**Regel:** Bij automatische poule-indeling MOETEN judoka's per gewichtsklasse + leeftijdsklasse correct gegroepeerd worden conform JudoBond regels. Een judoka mag NOOIT in een poule met onverenigbare gewichts-/leeftijdsverschillen worden geplaatst.

**Waarom:** Veiligheid van judoka's. Wettelijke aansprakelijkheid bij blessures door verkeerde indeling.

**Bewijs:** `PouleIndeelService`, `JudoboldRegelsValidator`, `tests/Feature/PouleIndelingValidatieTest.php`.

---

## C-06: Templates en presets worden bewaard tussen toernooien

**Regel:** `Template` en `Preset` records van een organisator blijven permanent bewaard ook na toernooi-afsluiting. Verwijderen kan alleen door de organisator zelf via expliciete actie in `/instellingen` — nooit automatisch door cleanup-jobs.

**Waarom:** Organisatoren zijn terugkerende klanten. Templates representeren uren werk.

**Bewijs:** Geen cascade-delete bij `Toernooi::deleted`, `tests/Feature/TemplatePersistenceTest.php`.

---

## C-07: Klantenbeheer (sitebeheerder) registreert wijzigingen

**Regel:** Wijzigingen via `/admin/klanten` (is_test, kortingsregeling, archived, verwijderd) worden gelogd met sitebeheerder-id, datum, en oude/nieuwe waarden. Geen stille mutaties.

**Waarom:** Audit-trail voor financiële beslissingen + verantwoording naar organisatoren bij geschillen.

**Bewijs:** `AdminKlantenController`, audit-log integratie, `OrganisatorPolicy`.

---

## C-08: AutoFix gaat altijd via hotfix-branch + PR

**Regel:** AutoFix push NOOIT direct naar `main` of een productie-branch. Elke fix gaat via `hotfix/autofix-{timestamp}` branch + Pull Request. Eigenaar mergt handmatig.

**Waarom:** Eigenaar behoudt controle. Voorkomt dat AutoFix de productie kapot maakt onder rate-limit-druk.

**Bewijs:** `AutoFixService` (HavunCore), GitHub workflow, eerdere SSH-incident november 2025.

---

## C-09: Wedstrijddag-scoring synchroniseert real-time naar alle scoreborden

**Regel:** Een score-update tijdens wedstrijddag wordt binnen 2 seconden zichtbaar op alle gekoppelde scoreborden (JSB-app, publieksdisplay, organisator-dashboard). WebSocket/Reverb is verplicht — geen poll-fallback langer dan 5 seconden.

**Waarom:** UX tijdens live toernooi. Organisatoren betalen voor real-time ervaring.

**Bewijs:** `ScoreUpdatedEvent`, Reverb config, `tests/Feature/ScoreSyncTest.php`.

---

## C-10: Productie-deploy verplicht via staging — geen uitzonderingen

**Regel:** Geen wijziging in `app/`, `routes/`, `database/migrations/` of `resources/views/` mag direct naar production. Verplichte route: lokale tests groen → staging-deploy → minimaal 24 uur observatie tijdens hoog-volume periode → production. Hotfixes alleen na expliciete eigenaar-instructie.

**Waarom:** Toernooidagen tolereren geen downtime. Een fout op zaterdag-ochtend = klanten boos op het hele platform.

**Bewijs:** `.github/workflows/deploy-staging.yml`, `.github/workflows/deploy-production.yml`, `docs/kb/runbooks/deploy.md` (HavunCore).

---

## Wat NIET in dit document hoort

- UI-keuzes (kleuren, layouts) — die mogen wijzigen
- Rapport-formats — gebruik aparte SPEC
- Performance-doelen → uptime-monitoring runbook
- Codestijl, naming → CLAUDE.md

## Wijzigingsprotocol

1. Eigenaar-akkoord (schriftelijk in commit-message)
2. Reden + datum in commit-message
3. Update bewakende tests
4. Heronderhoud van afhankelijke documenten

## Cross-references

- `CLAUDE.md` — projectregels
- `HavunCore/docs/kb/patterns/contracts-md-template.md` — concept
- `HavunCore/docs/kb/runbooks/test-repair-anti-pattern.md` — bij conflict
- `HavunCore/docs/audit/verbeterplan-q2-2026.md` — VP-14

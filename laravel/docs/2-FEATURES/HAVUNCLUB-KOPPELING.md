# HavunClub-koppeling (integratie-API)

> **Status:** geïmplementeerd (branch `feat/havunclub-koppeling`, 27 jun 2026).
> **Rol van JudoToernooi:** *leverancier* van de endpoints. HavunClub (de hub) is de aanroeper.
> **Centraal contract:** `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.

## Waarom

Een judoschool gebruikt drie losse SaaS-apps (HavunClub, JudoToernooi, HavunAdmin). HavunClub
is de hub: het pusht wedstrijdjudoka's naar JudoToernooi, schrijft ze in en haalt resultaten op.
JudoToernooi en HavunAdmin praten **niet** rechtstreeks met elkaar.

**Solo blijft solo.** Alles hieronder is puur additief: een nieuwe tabel, een nieuwe kolom en een
nieuwe routegroep. Zonder een aangemaakt club-token gebeurt er niets — JudoToernooi werkt volledig
zelfstandig zoals voorheen.

## Tenant = Organisator

JudoToernooi heeft geen apart "club"/tenant-model: de **Organisator** ís de tenant. Een
`ClubApiToken` koppelt een externe aanroeper (HavunClub) aan precies één Organisator. Daardoor
hoeft een request **geen** tenant-parameter mee te sturen — het token bepaalt de scope.

De menselijke herkenning bij het koppelen (is dit dezelfde judoschool?) gebeurt aan HavunClub-zijde
op `Organisator.email` + `Organisator.organisatie_naam`. JudoToernooi werkt daarna puur op het token.

## Token uitgeven

```bash
php artisan club:token-create {organisator-id-of-slug} --label=HavunClub
```

Het plaintext-token wordt **één keer** getoond → invullen in HavunClub `/koppelingen`. Patroon
gespiegeld op de scoreboard-device-tokens.

## Endpoints

Auth: `Authorization: Bearer <token>`. Middleware `club.token` + `throttle:api`. Ongeldig/ontbrekend
token → JSON `401` (zelfde shape als de scoreboard-API).

| Methode + pad | Doel | Request | Response |
|---|---|---|---|
| `POST /api/judokas` | upsert stam judoka | `judotoernooi_id?`, `havunclub_judoka_id?`, `voornaam`, `achternaam`, `geboortedatum`, `geslacht`, `band?` | `{ "id": <stam-judoka-id> }` |
| `POST /api/inschrijvingen` | judoka in toernooi | `toernooi_id`, `judoka_id` (stam-id), `naam?`, `band?`, `gewicht?` | `{ "id": <judoka-id> }` |
| `GET /api/toernooien/{toernooi}/resultaten` | uitslagen | — | `[ { judoka_id, stam_judoka_id, naam, gewichtsklasse, resultaat, partijen } ]` |
| `GET /api/toernooien/{toernooi}/weegkaart/{judoka}` | weegkaart-lookup | — (`{judoka}` = stam-id óf `havunclub_ref`) | `{ "token": "<uuid>", "url": "https://judotournament.org/weegkaart/<uuid>" }` of `404` |

### Veld-mapping (belangrijk)
HavunClub stuurt `voornaam`/`achternaam`/`geboortedatum`; `StamJudoka` slaat **`naam`** +
**`geboortejaar`** op. JudoToernooi mapt server-side:
- `naam = "<voornaam> <achternaam>"`
- `geboortejaar = jaar(geboortedatum)`
- `geslacht` genormaliseerd → `M`/`V` (accepteert m/man/male/jongen resp. v/f/vrouw/female/meisje)

### Idempotentie
- **Judoka-upsert:** match op (`organisator`, `judotoernooi_id`) of, als meegestuurd,
  (`organisator`, `havunclub_judoka_id` → kolom `stam_judokas.havunclub_ref`). Herhaalde sync → 1 rij.
- **Inschrijving:** match op (`toernooi_id`, `stam_judoka_id`). Tweede call geeft hetzelfde id terug.

### `resultaat`-waardenset
`resultaat` = `eindpositie` van de poule: **1 = goud, 2 = zilver, 3 = brons, …**. `partijen` =
gewonnen + verloren + gelijk. (Terug te koppelen in het centrale contract.)

### Guards (inschrijving)
Dezelfde organisator-regels als het coach-portaal: inschrijving open, max deelnemers, freemium-limiet.
Bij overtreding → `JudoToernooiException` → JSON `422` met `message` + `error_code` (4001/4002/4003).

### `gewicht` bij inschrijving (optioneel)
HavunClub mag een weeg-gewicht meesturen (`nullable|numeric|min:0|max:300`). Aanwezig → het
overschrijft `stam.gewicht` op de toernooi-`Judoka` en drijft de gewichtsklasse-bepaling. Afwezig →
val terug op het gewicht van de stam-judoka. Bij een herhaalde (idempotente) inschrijving wordt het
gewicht niet opnieuw gezet (net als `naam`/`band`).

### Weegkaart-lookup
`GET /api/toernooien/{toernooi}/weegkaart/{judoka}` — `{judoka}` is de stam-judoka-id (zoals
teruggegeven door `POST /judokas`) óf de `havunclub_ref`. Zoekt de toernooi-`Judoka` van die stam en
geeft `{ token, url }` terug (`token` = `Judoka.qr_code`, `url` = de publieke `weegkaart.show`-route).
`404` als de judoka niet in dit toernooi is ingeschreven. Tenant-gescoped op het token.

### Weegkaart in HavunClub inbedden (iframe/CSP)
De publieke weegkaart-page (`weegkaart/{token}`) mag ingebed worden vanuit de HavunClub-app. Alleen
voor die route zet `SecurityHeaders` `frame-ancestors 'self' https://havunclub.havun.nl` en laat het
`X-Frame-Options` weg (die kent geen multi-origin-vorm). Alle andere pagina's blijven `SAMEORIGIN`.

## Tenant-isolatie
Toernooi en stam judoka worden altijd gescoped op de organisator uit het token (`findOrFail` →
`404` bij vreemde tenant). Token A kan niets doen in de data van organisator B. Gedekt door de test
`cannot_enter_into_another_tenants_tournament`.

## Bestanden
- Migraties: `…_create_club_api_tokens_table`, `…_add_havunclub_ref_to_stam_judokas_table`
- Model `App\Models\ClubApiToken`; kolom `stam_judokas.havunclub_ref`
- Middleware `App\Http\Middleware\CheckClubToken` (alias `club.token` in `bootstrap/app.php`)
- Requests `App\Http\Requests\Api\{SyncJudokaRequest,InschrijvingRequest}`
- Service `App\Services\HavunClub\ClubInschrijvingService` (API-only; raakt het coach-portaal niet)
- Controller `App\Http\Controllers\Api\ClubSyncController`; routes in `routes/api.php`
- Command `club:token-create`; tests `tests/Feature/Api/ClubSyncTest.php` (7 tests)

## Open richting HavunClub / contract
- `geboortedatum` → JudoToernooi bewaart alleen het **jaar** (StamJudoka kent geen volledige datum).
- `havunclub_judoka_id` optioneel meesturen = robuustere idempotentie dan terugvertrouwen op het id.

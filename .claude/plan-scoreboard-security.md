# Plan — Scoreboard-API security fixes

> **Bron:** security-review 15 jul 2026 (HavunCore `docs/kb/reference/scoreboard-api-security-review-2026-07-15.md`).
> **Aanleiding:** Henk wil externe testers toelaten. Twee blokkerende lekken gevonden.
> **Status:** in uitvoering (Henk: "go").

## Wat er mis is

| # | Bevinding | Sev |
|---|-----------|-----|
| 1 | `POST /scoreboard/result` scope't `wedstrijd_id` niet op het toernooi van het token → cross-tenant write op elk toernooi | 🔴 |
| 2 | `POST /scoreboard/event` broadcast `$request->all()` incl. het gemergede `DeviceToegang`-model (api_token, code, telefoon, email) op een **publiek** Reverb-kanaal — empirisch bevestigd | 🔴 |
| 3 | Beschermde routes hebben geen rate limit | 🟡 |
| 4 | CORS default `allowed_origins: ['*']` op `/api/*` | 🔵 |

## Agenda

### A1 — Tenant-scoping op `result()`
`ScoreboardController::result()`: bepaal het toernooi van de wedstrijd
(`poule->blok->toernooi_id ?? poule->toernooi_id`) en weiger met **404** als dat niet
gelijk is aan `$toegang->toernooi_id`. 404 (niet 403) = isolatie, conform het bestaande
`ClubSyncController`-patroon ("findOrFail → 404 JSON when the resource is not in this tenant").
Wedstrijd zonder herleidbaar toernooi → ook weigeren (fail closed).

**Tests:** token toernooi A + wedstrijd toernooi B → 404 en wedstrijd ongewijzigd in DB;
eigen wedstrijd → blijft 200.

### A2 — Token-lek dichten (wortel + vangnet)
1. **Wortel:** `CheckScoreboardToken` zet de toegang in `$request->attributes` i.p.v.
   `$request->merge()`. Daarmee komt het model nooit meer in `$request->all()`/`input()`.
   Call-sites die `$request->input('device_toegang')` gebruiken (`tvLink`) moeten mee —
   `$request->get()` blijft werken (Symfony's `get()` leest attributes eerst), maar we maken
   het overal expliciet via `attributes->get()`.
2. **Vangnet:** `$hidden = ['api_token', 'device_token', 'code']` op `DeviceToegang`, zodat een
   toekomstig lek nooit meer het token exposet.
3. Zelfde patroon controleren in `CheckClubToken` en `LocalSyncAuth`.

**Tests:** broadcast-payload van `/event` bevat het token/code/e-mail niet (regressie-pin op
de exacte bug); `DeviceToegang::toArray()` bevat `api_token` niet.

### A3 — Rate limiting
**Niet** `throttle:api` — dat is 60/min **per IP**, en in een sporthal zitten alle matten achter
één NAT-IP; dat zou een echt toernooi platleggen. In plaats daarvan een eigen limiter
`scoreboard`: **120/min per Bearer-token** (fallback op IP als er geen token is). Per device dus,
niet per zaal.

**Tests:** limiter-registratie + 429 na overschrijding op één token; tweede token onaangetast.

### A4 — CORS expliciet
`config/cors.php` publiceren met `allowed_origins: [config('app.url')]` i.p.v. `*`.
Native apps sturen geen `Origin` en worden niet door CORS geraakt; de PWA/blade-views zijn
same-origin. Laag risico, maar het haalt de wildcard weg.

**Tests:** preflight van een vreemde origin krijgt geen `Access-Control-Allow-Origin: *`.

### A5 — Docs bijwerken (beide projecten)
- **JudoToernooi** `laravel/docs/2-FEATURES/SCOREBORD-APP.md` — stale: noemt "code + pincode"
  (pincode is verwijderd) en "AsyncStorage" (is SecureStore). Plus: security-model + tenant-scoping.
- **JudoScoreBoard** `docs/API.md` — stale: "code + pincode". Plus 404-gedrag bij vreemd toernooi
  en 429 documenteren.
- **JudoScoreBoard** `CONTRACTS.md` — C-02 zegt "JSB toont, JT bepaalt, geen lokale wijziging",
  maar de app POST uitslagen naar `/result`. C-03 noemt "OAuth-token", het is een 12-teken code.
  Contract ↔ code spreken elkaar tegen → **feitelijk vastleggen wat waar is, keuze aan Henk**.
- Handovers van beide projecten + de HavunCore-KB-review bijwerken.

## Bewust NIET nu

- **Private Reverb-channels** — vereist `withBroadcasting()` + auth-callbacks én een app-wijziging
  (de app doet geen `/broadcasting/auth`) + nieuwe APK-release. Aparte taak. Data op die kanalen
  is wedstrijdinfo die sowieso in de zaal zichtbaar is; ná A2 lekt er geen token meer.
- **Token-expiry/revocatie** — nodig vóór onbekende externe testers (je kunt toegang nu niet
  intrekken), maar raakt de organisator-UI. Aparte feature.
- `routes/channels.php` is dode code (nooit geladen) — opruimen of activeren hoort bij de
  private-channels-taak.

## Risico

- A2 raakt het middleware-contract: elke call-site van `device_toegang` moet mee, anders krijgt
  een controller `null`. Volledige suite moet groen blijven (264 tests).
- A3 fout ingesteld = toernooi plat. Daarom per token, ruim bemeten.
- A4 kan cross-origin calls breken die ik niet ken → test + terugdraaibaar via config.

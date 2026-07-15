⛔ STOP: Bij brainstorm/planning-vragen schrijf je NOOIT code en voer je GEEN acties uit tot Henk expliciet "ga maar" typt. Eerst luisteren en plannen.
📍 SCOPE: Alleen JudoToernooi. Ga je naar een ander project? Kill deze sessie (Ctrl+C) en start een nieuwe.

## ✅ Verplicht bij sessiestart

1. Lees `.claude/handover.md` — staat WAT er nu speelt en wat de volgende stap is
2. Als `.claude/blueprint.md` bestaat → lees het, dat IS het implementatieplan
3. Zoek KB: `cd D:/GitHub/HavunCore && php artisan docs:search "<onderwerp>" --project=judotoernooi`

## Grote taken → Gemini first

```bash
/arch --project=judotoernooi "beschrijving"
```
Blueprint landt in `D:\GitHub\JudoToernooi\.claude\blueprint.md`. Daarna `/mpc` + "ga maar".

# JudoToernooi

**Stack:** Laravel 11 + Blade + Alpine.js + Tailwind — https://judotournament.org
**SaaS:** multi-tenant toernooi-management voor judoscholen
**Server:** /var/www/judotoernooi/repo-prod op 188.245.159.115
**Lokaal:** `cd laravel && php artisan serve --port=8007`
**KB zoeken:** `cd D:/GitHub/HavunCore && php artisan docs:search "<onderwerp>"`

## Sessie-start sync (AutoFix kan server wijzigen)

```bash
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-prod && git add -A && git diff --cached --quiet || git commit -m 'autofix' && git push"
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-staging && git add -A && git diff --cached --quiet || git commit -m 'autofix' && git push"
cd D:\GitHub\JudoToernooi && git pull
```

## Project-specifieke feiten

- **Auth guard:** `organisator` (NIET `web`) — `auth('organisator')->user()`
- **Artisan:** altijd `cd laravel &&` prefix (repo-root heeft geen artisan)
- **DB:** MySQL prod / SQLite lokaal — NOOIT `php artisan test` op staging/production (wist MySQL data)
- **Staging:** `/var/www/judotoernooi/repo-staging` · Production: `/var/www/judotoernooi/repo-prod`
- **AutoFix:** actief op production. Max 2 pogingen, rate limit 60 min
- **Realtime:** Reverb/WebSockets — GEEN polling (setInterval/fetch)
- **Broadcast events:** verplicht `use \App\Events\Concerns\SafelyBroadcasts;`
- **Deploy:** `git pull` in repo-pad, NIET in symlink

## Tests

```bash
cd laravel && php artisan test --no-coverage
```

## Verboden zonder overleg

SSH keys, credentials, `.env`, composer/npm installs, prod migrations, systemd/cron.

## MD-docs schrijven — hou ze leesbaar voor Claude

Een te lang doc wordt niet gelezen: het verdringt andere docs uit de context, en de KB indexeert
alleen het **begin** van een bestand (~2000-8000 tekens) — alles daarna is onvindbaar via `docs:search`.

- **Max:** KB-doc/runbook 200 regels · CLAUDE.md 120 · plan/blueprint 300 · handover 15-30 regels per sessie
- **Hiërarchie:** conclusie + status bovenaan, tabel in het midden, onderbouwing onderaan
- **Te groot?** Splitsen in index + deeldocs. Niet persen tot telegramstijl — onleesbaar is niet kort
- **Handover:** er is er **één** en die werk je **bij** — nooit een sessieblok toevoegen.
  Afgeronde taken eruit, nieuwe erbij. Levende status, geen logboek (git bewaart de historie)

Volledig: `HavunCore/docs/kb/standards/md-doc-grootte.md`

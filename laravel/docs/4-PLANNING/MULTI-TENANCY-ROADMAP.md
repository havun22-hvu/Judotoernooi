---
title: Planning: Multi-Tenancy
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Planning: Multi-Tenancy


> **Status:** ON HOLD — subdomeinen niet nodig, slug-based routing is live
> **Reden:** Subdomeinen niet nodig op dit moment, mogelijk later
> **Let op:** Multi-tenancy is al werkend via organisator slugs (`/{slug}/toernooi/...`)
> **Doel:** Wereldwijde SaaS-applicatie met tenant isolatie en meertaligheid

## Overzicht

De JudoToernooi applicatie wordt voorbereid voor gebruik als SaaS platform waar meerdere organisaties (tenants) elk hun eigen geïsoleerde omgeving hebben.

> Dit is een index-doc — de uitwerking staat in `MULTI-TENANCY/`.

## Implementatie Stappen

### Fase 1: Basis Tenancy Setup

- [ ] `stancl/tenancy` installeren
- [ ] Tenancy config aanpassen
- [ ] Central database migraties maken
- [ ] Tenant database migraties aanpassen
- [ ] Route configuratie (tenant routes vs central routes)

### Fase 2: Tenant Management

- [ ] Tenant CRUD in admin panel
- [ ] Domain management
- [ ] Tenant provisioning (create database, run migrations)
- [ ] Tenant deletion (soft delete + later hard delete)

### Fase 3: Bestaande Code Aanpassen

- [ ] Middleware voor tenant context
- [ ] Auth guards per tenant
- [ ] Queue tenant-awareness
- [ ] Storage per tenant
- [ ] Cache per tenant

### Fase 4: Meertaligheid

- [ ] `spatie/laravel-translatable` installeren
- [ ] Translatable velden toevoegen aan models
- [ ] Language files aanmaken (nl, en, de, fr, es)
- [ ] Locale switcher UI
- [ ] Locale middleware

### Fase 5: Subscription/Billing

- [ ] Plans tabel vullen
- [ ] Feature gates (max tournaments, max judokas)
- [ ] Stripe/Mollie integratie voor subscriptions
- [ ] Trial period handling

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [ARCHITECTUUR.md](MULTI-TENANCY/ARCHITECTUUR.md) | Je wilt weten waarom database-per-tenant en subdomeinen gekozen zijn, met welke nadelen, en waarom `stancl/tenancy` het package is. |
| [DATABASE-SCHEMA.md](MULTI-TENANCY/DATABASE-SCHEMA.md) | Je bouwt of wijzigt de central database (tenants, domains, plans) of moet weten wat er in de tenant-database hoort. |
| [MEERTALIGHEID.md](MULTI-TENANCY/MEERTALIGHEID.md) | Je werkt aan vertaalbare velden, language files of locale-detectie (Fase 4). |
| [ROUTES-EN-CONFIG.md](MULTI-TENANCY/ROUTES-EN-CONFIG.md) | Je zet tenant- vs central-routes op of moet `config/tenancy.php` invullen (Fase 1). |
| [MIGRATIE-EN-OPEN-VRAGEN.md](MULTI-TENANCY/MIGRATIE-EN-OPEN-VRAGEN.md) | Je plant de overgang van de huidige data, of zoekt de openstaande beslissingen (pricing, tenant-beheer) en de taalprioriteit per land. |

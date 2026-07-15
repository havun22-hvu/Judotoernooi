---
title: Multi-tenancy - Architectuur en tenant-identificatie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Multi-tenancy - Architectuur en tenant-identificatie

> Onderdeel van [Multi-tenancy roadmap](../MULTI-TENANCY-ROADMAP.md).

## Gekozen Architectuur

### Database per Tenant

```
┌─────────────────────────────────────────────────────────────────┐
│ Central Database (shared)                                        │
│ - tenants (id, domain, name, settings, locale, plan, status)    │
│ - users (global admins only)                                     │
│ - plans (subscription plans)                                     │
└─────────────────────────────────────────────────────────────────┘
        │
        ├──────────────────┬──────────────────┬──────────────────┐
        ▼                  ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ tenant_jbn_nl │  │ tenant_ijf    │  │ tenant_club_de│  │ tenant_...    │
│ - toernooien  │  │ - toernooien  │  │ - toernooien  │  │ - ...         │
│ - judokas     │  │ - judokas     │  │ - judokas     │  │               │
│ - poules      │  │ - poules      │  │ - poules      │  │               │
│ - clubs       │  │ - clubs       │  │ - clubs       │  │               │
│ - ...         │  │ - ...         │  │ - ...         │  │               │
└───────────────┘  └───────────────┘  └───────────────┘  └───────────────┘
```

### Waarom Database per Tenant?

| Voordeel | Uitleg |
|----------|--------|
| **Data isolatie** | 100% gegarandeerd, geen risico op cross-tenant leaks |
| **GDPR compliance** | Tenant verwijderen = DROP DATABASE |
| **Performance** | Kleine databases, snelle queries |
| **Backups** | Per tenant backup/restore mogelijk |
| **Schaalbaarheid** | Horizontaal: databases over servers verdelen |
| **Maatwerk** | Per tenant custom migraties mogelijk |

### Nadelen (acceptabel)

| Nadeel | Mitigatie |
|--------|-----------|
| Complexere migraties | Automated migration runner |
| Meer databases | Cloud DB management (managed MySQL) |
| Cross-tenant queries | Via central database of queue jobs |

## Tenant Identificatie

### Opties

1. **Subdomain** (aanbevolen): `jbn.judotournament.org`
2. **Path**: `judotournament.org/jbn/...`
3. **Header/Token**: API-based identificatie

### Gekozen: Subdomain

```
jbn.judotournament.org      → tenant: jbn_nl
ijf.judotournament.org      → tenant: ijf
clubname.judotournament.org → tenant: clubname
```

**Custom domains ook mogelijk:**
```
toernooi.judobond.nl → tenant: jbn_nl (via DNS CNAME)
```

## Package Keuze

### stancl/tenancy (gekozen)

- Mature, goed onderhouden
- Database per tenant out-of-the-box
- Subdomain identificatie
- Automatic tenant switching
- Queue tenant-awareness
- Storage per tenant

**Installatie:**
```bash
composer require stancl/tenancy
php artisan tenancy:install
```


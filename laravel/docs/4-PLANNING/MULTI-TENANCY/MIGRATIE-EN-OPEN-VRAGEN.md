---
title: Multi-tenancy - Migratie, open vragen en notities
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Multi-tenancy - Migratie, open vragen en notities

> Onderdeel van [Multi-tenancy roadmap](../MULTI-TENANCY-ROADMAP.md).

## Migratie Bestaande Data

Bij migratie van huidige single-tenant naar multi-tenant:

1. Maak tenant voor huidige organisatie(s)
2. Kopieer data naar tenant database
3. Verificatie
4. Switch DNS naar nieuwe setup
5. Cleanup oude data

## Top 10 Judo Landen (voor taalprioriteit)

| Land | Judoka's (geschat) | Taal |
|------|-------------------|------|
| 1. Brazilië | ~2 miljoen (incl. BJJ) | pt |
| 2. Frankrijk | ~500.000 | fr |
| 3. Japan | ~160.000 | ja |
| 4. Duitsland | ~160.000 | de |
| 5. Rusland | ~150.000 | ru |
| 6. Zuid-Korea | ~100.000 | ko |
| 7. Mongolië | ~50.000 | mn |
| 8. Nederland | ~40.000 | nl |
| 9. VS | ~40.000 | en |
| 10. UK | ~30.000 | en |

**Aanbevolen taalvolgorde:** nl (basis), en (internationaal), fr, de, pt, es, ja

## Open Vragen

1. ~~**Welke talen prioriteit?**~~ → Zie top 10 judo landen
2. ~~**Subdomain vs path?**~~ → Subdomain (maar later)
3. **Pricing model?** Per toernooi, per judoka, flat rate?
4. **Wie beheert tenants?** Zelf-registratie of handmatig?

## Notities

- Huidige codebase is NIET tenant-aware
- Alle `toernooi_id` checks zijn voldoende binnen tenant context
- Mollie Connect werkt al per organisator, past goed bij multi-tenant

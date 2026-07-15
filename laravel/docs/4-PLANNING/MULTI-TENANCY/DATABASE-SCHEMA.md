---
title: Multi-tenancy - Database schema
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Multi-tenancy - Database schema

> Onderdeel van [Multi-tenancy roadmap](../MULTI-TENANCY-ROADMAP.md).

## Database Schema

### Central Database

```sql
-- tenants tabel
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,  -- slug: 'jbn_nl', 'ijf'
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),

    -- Domains
    -- (via domains tabel, many-to-many)

    -- Settings
    locale VARCHAR(10) DEFAULT 'nl',
    timezone VARCHAR(50) DEFAULT 'Europe/Amsterdam',
    settings JSON,  -- tenant-specific settings

    -- Subscription
    plan_id BIGINT UNSIGNED,
    trial_ends_at TIMESTAMP NULL,

    -- Status
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- domains tabel (voor custom domains)
CREATE TABLE domains (
    id BIGINT PRIMARY KEY,
    domain VARCHAR(255) UNIQUE,
    tenant_id VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- plans tabel (subscription plans)
CREATE TABLE plans (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    price DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'EUR',
    interval ENUM('monthly', 'yearly'),

    -- Limits
    max_tournaments INT DEFAULT 10,
    max_judokas_per_tournament INT DEFAULT 500,
    max_organisators INT DEFAULT 5,

    features JSON,  -- ['multi-mat', 'eliminatie', 'betalingen', ...]

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Tenant Database

Bestaande tabellen blijven identiek:
- `toernooien`
- `judokas`
- `poules`
- `clubs`
- `organisators`
- `coaches`
- etc.

**Geen tenant_id kolommen nodig** - complete isolatie.


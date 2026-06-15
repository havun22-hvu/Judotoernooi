---
title: SEO Specificaties - JudoToernooi
type: reference
scope: judotoernooi
last_check: 2026-04-22
---

# SEO Specificaties - JudoToernooi

## Overzicht

JudoToernooi ondersteunt NL + EN via `__()` helpers. Alle SEO tags zijn taalafhankelijk.

## Componenten

### `<x-seo />` Blade Component

**Locatie:** `resources/views/components/seo.blade.php`

| Prop | Type | Default | Beschrijving |
|------|------|---------|-------------|
| `title` | string | null | Page title voor OG/Twitter |
| `description` | string | null | Meta description + OG description |
| `canonical` | string | auto | Canonical URL (default: `config('app.url') + path`) |
| `ogImage` | string | `/icon-512x512.png` | OG image URL |
| `type` | string | `website` | OG type (`website`, `article`) |
| `noindex` | bool | false | Zet `noindex, nofollow` |

**Output:** meta description, canonical, hreflang (nl+en+x-default), OG tags, Twitter Card tags.

### Gebruik per pagina

| Pagina | Component | Props |
|--------|-----------|-------|
| Homepage (`home.blade.php`) | `<x-seo>` | title, description |
| Help (`help.blade.php`) | `<x-seo>` via `@push('seo')` | title, description |
| Legal pages (`legal-layout.blade.php`) | `<x-seo>` | title |
| Publieke toernooi (`publiek/index.blade.php`) | `<x-seo>` | title, description, type=article, **noindex bij 0 deelnemers** |
| Error pages (`errors/layout.blade.php`) | `<x-seo>` | noindex=true |

> **Indexeerbaarheid toernooi-pagina's:** een toernooi zonder deelnemers (judoka's) is *thin content* (lege/test-toernooien) en krijgt `noindex`. De live-pagina blijft bereikbaar via directe link/QR; alleen Google-indexering wordt onderdrukt. De sitemap (`SitemapController`) past dezelfde regel toe via `whereHas('judokas')`, zodat sitemap en `<meta robots>` één bron van waarheid delen.

## robots.txt

**Locatie:** `public/robots.txt`

- Allow: homepage, help, legal pages, sitemap
- Disallow: auth routes, admin, API, device/role access, weegkaart/coach-kaart

## Sitemap

**Route:** `GET /sitemap.xml`
**Controller:** `App\Http\Controllers\SitemapController`

Bevat:
- Statische pagina's (home, help, legal pages) met prioriteit + lastmod
- Actieve toernooien (niet afgesloten, datum < 3 maanden geleden) met lastmod
- hreflang per URL (nl + en)

## JSON-LD Structured Data

### Homepage
- `Organization` schema (Havun, logo, contactpunt, talen)
- `WebSite` schema (naam, URL, talen, publisher)
- `SoftwareApplication` schema (JudoToernooi, gratis, sport-categorie, featureList, talen)
- `FAQPage` schema (3 veelgestelde vragen met antwoorden, NL+EN vertaald)

### Publieke toernooi pagina
- `SportsEvent` schema (naam, sport=Judo, datum, locatie, organisator, aantal deelnemers)
- `BreadcrumbList` schema (Home → Organisator → Toernooi)

## Performance

### Nginx (server-side)
- **Gzip compression** op alle content types (CSS, JS, JSON, XML, etc.)
- **Cache headers** voor static assets: 30 dagen (CSS, JS, images, fonts), 1 uur (XML, JSON, manifests)
- **HSTS** header in production (via SecurityHeaders middleware)

### Preconnect
- Publieke toernooi pagina: `<link rel="preconnect">` + `<link rel="dns-prefetch">` voor `js.pusher.com`

### App Layout
- `@stack('seo')` in `<head>` voor child pages die SEO tags willen toevoegen via `@push('seo')`

## Google Analytics (GA4)

- **Measurement ID:** `G-42KGYDWS5J`
- **Property:** JudoToernooi (Sport categorie)
- **Laden:** Alleen in production (`app()->environment('production')`)
- **Locatie:** Via `<x-seo />` component (alle publieke pagina's)
- **Dashboard:** https://analytics.google.com

### Google Search Console

- **URL:** https://search.google.com/search-console
- **Verificatie:** DNS TXT record (via mijn.host nameservers)
- **Sitemap:** Ingediend (`/sitemap.xml`)

### Bing Webmaster Tools

- **URL:** https://www.bing.com/webmasters
- **Verificatie:** Geïmporteerd vanuit Google Search Console
- **Sitemap:** Handmatig ingediend (`/sitemap.xml`)

## Twitter Card

Type: `summary_large_image` (grotere preview bij delen op social media)

## hreflang + canonical Strategie

Locale switching gebruikt de `?locale=xx` query parameter. **Eén indexeerbare URL per taal**, met self-referentiële canonicals zodat hreflang en canonical elkaar niet tegenspreken:

- **Default-taal (`nl`)** → param-loze URL (`/help`) = canonical én x-default
- **Overige talen (`en`)** → `?locale=en`-URL (`/help?locale=en`) = eigen canonical
- `?locale=nl` consolideert naar de param-loze URL (canonical wijst daarheen)

```html
<!-- op /help (nl, default) én /help?locale=nl -->
<link rel="canonical" href="https://judotournament.org/help">
<link rel="alternate" hreflang="nl" href="https://judotournament.org/help">
<link rel="alternate" hreflang="en" href="https://judotournament.org/help?locale=en">
<link rel="alternate" hreflang="x-default" href="https://judotournament.org/help">

<!-- op /help?locale=en -->
<link rel="canonical" href="https://judotournament.org/help?locale=en">
```

> **Waarom:** Google vereist dat hreflang-doelen self-canonical én indexeerbaar zijn. Eerder wezen de hreflang-tags naar `?locale=nl`/`?locale=en` terwijl de canonical altijd param-loos was → conflict → "Gecrawld – momenteel niet geïndexeerd". De basis-URL wordt opgebouwd uit `config('app.url')`, wat tegelijk `www`→non-`www` consolideert. `SitemapController` gebruikt exact dezelfde `$localeUrl`-logica.

## lang Attribuut

Alle layouts gebruiken `{{ app()->getLocale() }}` in het `<html lang="">` attribuut:
- `home.blade.php`
- `publiek/index.blade.php`
- `errors/layout.blade.php`
- `layouts/print.blade.php`
- `legal-layout.blade.php` (gebruikte al `str_replace('_', '-', app()->getLocale())`)

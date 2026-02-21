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
| Legal pages (`legal-layout.blade.php`) | `<x-seo>` | title |
| Publieke toernooi (`publiek/index.blade.php`) | `<x-seo>` | title, description, type=article |
| Error pages (`errors/layout.blade.php`) | `<x-seo>` | noindex=true |

## robots.txt

**Locatie:** `public/robots.txt`

- Allow: homepage, help, legal pages, sitemap
- Disallow: auth routes, admin, API, device/role access, weegkaart/coach-kaart

## Sitemap

**Route:** `GET /sitemap.xml`
**Controller:** `App\Http\Controllers\SitemapController`

Bevat:
- Statische pagina's (home, help, legal pages) met prioriteit
- Actieve toernooien (niet afgesloten, datum < 3 maanden geleden)
- hreflang per URL (nl + en)

## JSON-LD Structured Data

### Homepage
- `Organization` schema (Havun, logo, contactpunt, talen)
- `WebSite` schema (naam, URL, talen, publisher)
- `SoftwareApplication` schema (JudoToernooi, gratis, sport-categorie, featureList, talen)
- `FAQPage` schema (3 veelgestelde vragen met antwoorden, NL+EN vertaald)

### Publieke toernooi pagina
- `SportsEvent` schema (naam, sport=Judo, datum, locatie, organisator, aantal deelnemers)

## Performance

### Preconnect
- Publieke toernooi pagina: `<link rel="preconnect">` + `<link rel="dns-prefetch">` voor `js.pusher.com`

## Twitter Card

Type: `summary_large_image` (grotere preview bij delen op social media)

## hreflang Strategie

Locale switching gebruikt `?locale=nl` / `?locale=en` query parameter.
hreflang tags worden automatisch gegenereerd door `<x-seo />`:

```html
<link rel="alternate" hreflang="nl" href="...?locale=nl">
<link rel="alternate" hreflang="en" href="...?locale=en">
<link rel="alternate" hreflang="x-default" href="...">
```

## lang Attribuut

Alle layouts gebruiken `{{ app()->getLocale() }}` in het `<html lang="">` attribuut:
- `home.blade.php`
- `publiek/index.blade.php`
- `errors/layout.blade.php`
- `layouts/print.blade.php`
- `legal-layout.blade.php` (gebruikte al `str_replace('_', '-', app()->getLocale())`)

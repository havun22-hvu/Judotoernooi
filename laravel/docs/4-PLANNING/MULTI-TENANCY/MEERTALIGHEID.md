---
title: Multi-tenancy - Meertaligheid
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Multi-tenancy - Meertaligheid

> Onderdeel van [Multi-tenancy roadmap](../MULTI-TENANCY-ROADMAP.md).

## Meertaligheid

### Package: spatie/laravel-translatable

Voor database content (toernooi namen, categorieën):

```php
// Model
use Spatie\Translatable\HasTranslations;

class Toernooi extends Model
{
    use HasTranslations;

    public $translatable = ['naam', 'locatie', 'omschrijving'];
}

// Gebruik
$toernooi->naam = 'Open Westfries Judo Toernooi';  // default locale
$toernooi->setTranslation('naam', 'en', 'Open Westfries Judo Tournament');
$toernooi->setTranslation('naam', 'de', 'Offenes Westfries Judo Turnier');
```

### Laravel Localization

Voor UI teksten (blade views, validatie):

```
/lang
  /nl
    messages.php
    validation.php
  /en
    messages.php
    validation.php
  /de
    messages.php
    validation.php
```

### Locale Detectie

1. Tenant default locale
2. User preference (cookie/session)
3. Browser Accept-Language header
4. Fallback: 'nl'


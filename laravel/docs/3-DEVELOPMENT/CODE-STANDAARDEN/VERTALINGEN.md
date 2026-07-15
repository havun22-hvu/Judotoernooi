---
title: Vertalingen (i18n)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Vertalingen (i18n)

> Onderdeel van [Code-standaarden](../CODE-STANDAARDEN.md).

## 14. Vertalingen (i18n)

### Kernregel

**ALLE gebruikersgerichte tekst moet door `__()` vertaalfunctie.** Geen hardcoded Nederlandse of Engelse strings.

### Blade Templates (HTML)

```blade
{{-- ✓ GOED --}}
<p>{{ __('Voer je PIN in') }}</p>
<button>{{ __('Inloggen') }}</button>

{{-- ✗ FOUT --}}
<p>Voer je PIN in</p>
<button>Inloggen</button>
```

### JavaScript in Blade (KRITIEK!)

JS in `<script>` blokken kan geen `__()` aanroepen. Gebruik een **translations object** bovenaan het script:

```javascript
// ✓ GOED - translations object met Blade __() calls
const __t = {
    incorrectPin: @json(__('Onjuiste PIN')),
    somethingWrong: @json(__('Er ging iets mis')),
};

showPinError(__t.incorrectPin);

// ✗ FOUT - hardcoded tekst in JS
showPinError('Onjuiste PIN');
document.getElementById('title').textContent = 'Welkom terug';
```

### Taalbestanden

| Bestand | Inhoud |
|---------|--------|
| `lang/nl.json` | Nederlandse strings (brontaal, hoeft niet voor `__()` keys) |
| `lang/en.json` | Engelse vertalingen (VERPLICHT voor elke `__()` key) |

### Checklist bij nieuwe tekst

- [ ] Blade HTML: `{{ __('tekst') }}` gebruikt?
- [ ] JavaScript: `__t` object met `@json(__('tekst'))` gebruikt?
- [ ] `lang/en.json`: Engelse vertaling toegevoegd?
- [ ] Check script: `php artisan check:translations` gedraaid?

### Check Script

```bash
php artisan check:translations
```

Detecteert hardcoded Nederlandse tekst in `<script>` blokken van Blade files.

---

*Laatst bijgewerkt: 1 maart 2026*

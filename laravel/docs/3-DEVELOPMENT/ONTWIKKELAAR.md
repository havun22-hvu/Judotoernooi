---
title: Ontwikkelaar Gids
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Ontwikkelaar Gids

> Index-doc voor ontwikkelaars aan JudoToernooi: architectuur bovenaan, de rest in deeldocs.
> Begin hier als je niet weet waar je code hoort te landen.

## Architectuur

Het project volgt een Service-gebaseerde architectuur:

```
Request → Controller → Service → Model → Database
                ↑
             Response
```

### Controllers

Controllers zijn dun en delegeren naar Services:

```php
class PouleController extends Controller
{
    public function genereer(Toernooi $toernooi): RedirectResponse
    {
        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);
        return redirect()->back()->with('success', 'Poules gegenereerd');
    }
}
```

### Services

Services bevatten de business logic:

```php
class PouleIndelingService
{
    public function genereerPouleIndeling(Toernooi $toernooi): array
    {
        // Complexe logica hier
    }
}
```

### Models

Eloquent models voor database interactie:

```php
class Judoka extends Model
{
    protected $fillable = ['naam', 'geboortejaar', ...];

    public function poules(): BelongsToMany
    {
        return $this->belongsToMany(Poule::class);
    }
}
```

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [SETUP-EN-TESTING.md](./ONTWIKKELAAR/SETUP-EN-TESTING.md) | Je zet het project lokaal op, of je schrijft een unit/feature test en zoekt de code style-regels |
| [CONCEPTEN.md](./ONTWIKKELAAR/CONCEPTEN.md) | Je raakt de judoka code, de poule-verdeling, het wedstrijdschema of de blokverdeling (25%-limiet, balans slider) |
| [FASEN-EN-STATISTIEKEN.md](./ONTWIKKELAAR/FASEN-EN-STATISTIEKEN.md) | `aantal_judokas`/`aantal_wedstrijden` klopt niet, of je twijfelt of iets bij voorbereiding of toernooidag hoort |
| [MIGRATIES-EN-DEBUGGING.md](./ONTWIKKELAAR/MIGRATIES-EN-DEBUGGING.md) | Je maakt een migratie, rollt terug, cachet voor productie, leest logs of deployt |

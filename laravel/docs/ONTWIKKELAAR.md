# Ontwikkelaar Gids

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

## Development Setup

### Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+

### Setup

```bash
# Clone en installeer
git clone <repo>
cd laravel
composer install
npm install

# Configuratie
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Start servers
php artisan serve
npm run dev
```

## Code Standaarden

### PHP

- PSR-12 code style
- Type hints overal
- PHPDoc voor complexe methodes

```php
/**
 * Generate pool division for a tournament
 *
 * @param Toernooi $toernooi The tournament to generate pools for
 * @return array Statistics about generated pools
 */
public function genereerPouleIndeling(Toernooi $toernooi): array
{
    // ...
}
```

### Laravel Specifiek

- Route model binding gebruiken
- Form Requests voor validatie
- Resource controllers waar mogelijk
- Enums voor vaste waarden

## Testing

### Unit Tests

```bash
php artisan test --filter=PouleIndelingServiceTest
```

```php
class PouleIndelingServiceTest extends TestCase
{
    public function test_maakt_optimale_poules_voor_vijf_judokas()
    {
        $service = new PouleIndelingService();
        $judokas = Judoka::factory()->count(5)->make();

        $poules = $service->maakOptimalePoules($judokas);

        $this->assertCount(1, $poules);
        $this->assertCount(5, $poules[0]);
    }
}
```

### Feature Tests

```php
class ToernooiTest extends TestCase
{
    public function test_kan_toernooi_aanmaken()
    {
        $response = $this->post('/toernooi', [
            'naam' => 'Test Toernooi',
            'datum' => '2025-10-25',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('toernooien', ['naam' => 'Test Toernooi']);
    }
}
```

## Belangrijke Concepten

### Judoka Code

Unieke code voor poule-indeling:

```
LLGGBG
LL = Leeftijdscode (08, 10, 12, 15, 18, 21)
GG = Gewichtscode (20, 23, 26, ...)
B  = Bandcode (0-6)
G  = Geslacht (M/V)

Voorbeeld: "123445M" = B-pupillen, -34kg, oranje band, man
```

### Poule Verdeling Algoritme

1. Groepeer judoka's op leeftijd + gewicht + (geslacht bij -15+)
2. Per groep: verdeel in optimale poules (target: 5)
3. Vermijd poules van 1-2 (penalty score)
4. Balanceer grootte over poules

### Wedstrijd Schema

Optimale volgorde om rust te geven:

- **3 judoka's**: Dubbele ronde (6 wedstrijden)
- **4 judoka's**: 1-2, 3-4, 1-3, 2-4, 1-4, 2-3
- **5+ judoka's**: Round-robin

## Database Migraties

### Nieuwe Migratie

```bash
php artisan make:migration add_column_to_judokas_table
```

### Rollback

```bash
php artisan migrate:rollback --step=1
```

## Caching

Voor productie:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Debugging

### Logs

```bash
tail -f storage/logs/laravel.log
```

### Tinker

```bash
php artisan tinker

>>> $toernooi = Toernooi::first();
>>> $toernooi->judokas()->count();
>>> $toernooi->poules()->sum('aantal_wedstrijden');
```

## Deployment

Zie [INSTALLATIE.md](./INSTALLATIE.md) voor volledige instructies.

Checklist:
1. `composer install --no-dev`
2. `npm run build`
3. `php artisan migrate --force`
4. `php artisan config:cache`
5. `php artisan route:cache`
6. `php artisan view:cache`

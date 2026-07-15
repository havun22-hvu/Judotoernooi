---
title: Setup, code standaarden en testing
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Setup, code standaarden en testing

> Onderdeel van [Ontwikkelaar Gids](../ONTWIKKELAAR.md).

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


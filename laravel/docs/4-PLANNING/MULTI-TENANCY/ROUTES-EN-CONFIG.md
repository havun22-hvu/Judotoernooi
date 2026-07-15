---
title: Multi-tenancy - Routes en config
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Multi-tenancy - Routes en config

> Onderdeel van [Multi-tenancy roadmap](../MULTI-TENANCY-ROADMAP.md).

## Route Structuur

```php
// Central routes (geen tenant context)
Route::domain('judotournament.org')->group(function () {
    Route::get('/', 'HomeController@index');  // Landing page
    Route::get('/pricing', 'PricingController@index');
    Route::get('/register', 'RegisterController@showForm');  // Tenant registration
});

// Admin routes (super admin)
Route::domain('admin.judotournament.org')->group(function () {
    Route::get('/tenants', 'Admin\TenantController@index');
    // ...
});

// Tenant routes (met tenant context)
Route::middleware(['tenant'])->group(function () {
    // Alle bestaande routes
    Route::get('/organisator/dashboard', ...);
    Route::get('/toernooi/{toernooi}', ...);
    // ...
});
```

## Config Wijzigingen

### config/tenancy.php

```php
return [
    'tenant_model' => \App\Models\Tenant::class,

    'identification_strategy' => 'domain',  // of 'path'

    'database' => [
        'prefix' => 'tenant_',
        'suffix' => '',
        'template' => null,  // of 'tenant_template' voor pre-seeded data
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'features' => [
        // Stancl\Tenancy\Features\TenantConfig::class,
        // Stancl\Tenancy\Features\CrossDomainRedirect::class,
    ],
];
```


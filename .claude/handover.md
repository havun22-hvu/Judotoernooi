# Session Handover: 31 januari 2026 (deel 2)

## Wat is gedaan:

### Noodplan print schema's (eerder)
- Toernooinaam + datum als table rows boven wedstrijdschema's
- Zelfde breedte als tabel via colspan

### Deel 2 fixes (door gebruiker gedaan):
- JudokaController route missing organisator
- Coach portal band select reset
- Band kyu cleanup (database)
- Sync deadline check
- Toast overlapt menu
- BlokController route missing organisator
- CheckToernooiRol middleware routes
- Noodplan script tag escape
- Noodplan live schema dubbele potjes

## Openstaand:

**Noodplan print headers** - Gebruiker vroeg eerder "lukt het niet??" maar geen screenshot/beschrijving ontvangen. Mogelijk werkt de header niet zoals verwacht. Vraag bij volgende sessie om verduidelijking als nodig.

## Code structuur noodplan print:

```
ingevuld-schema.blade.php (Matrix)
└── <table class="schema-table">
    └── <thead>
        ├── <tr class="title-row"> - toernooinaam + datum (donker)
        ├── <tr class="info-row"> - poule info + checkbox (licht)
        └── <tr class="header-row"> - kolom headers

index.blade.php (Live)
└── generateLiveHTML() - zelfde structuur, JS generated
```

## Server paden:
- Staging: `/var/www/staging.judotoernooi/laravel`
- Production: `/var/www/judotoernooi/laravel`

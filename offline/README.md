# JudoToernooi Offline Noodpakket

Standalone server die op een Windows laptop draait voor wedstrijddagen zonder internet.

## Bouwen

**Eenmalig, op je dev machine:**

```powershell
cd D:\GitHub\JudoToernooi
powershell -ExecutionPolicy Bypass -File offline\build.ps1
```

Het script doet automatisch:
1. Go compiler downloaden (als niet geinstalleerd)
2. Portable PHP 8.2 downloaden van windows.php.net
3. Laravel app strippen (wedstrijddag-only kopie)
4. Go launcher compileren naar `launcher.exe`

Output staat in `offline/build/`.

## Architectuur

```
Organisator downloadt van judotournament.org:
┌─────────────────────────────────────────────────┐
│  noodpakket_[toernooinaam].zip                  │
│  ├── noodpakket.exe    (Go launcher, ~5MB)      │
│  ├── bundle.zip        (auto-extracted):        │
│  │   ├── php/          (portable PHP, ~29MB)    │
│  │   ├── laravel/      (gestripte app, ~30MB)   │
│  │   ├── database.sqlite (toernooi data, ~1MB)  │
│  │   └── license.json  (3 dagen geldig)         │
│  └── LEES MIJ.txt                               │
└─────────────────────────────────────────────────┘
```

## Hoe het werkt

1. Organisator klikt "Download Server Pakket" op judotournament.org
2. Server combineert pre-built components + toernooi-specifieke SQLite + license
3. Download als .zip (~65MB)
4. Organisator pakt uit en dubbelklikt `noodpakket.exe`
5. Launcher extraxt `bundle.zip` → start PHP server → opent browser
6. Tablets verbinden via `http://[laptop-ip]:8000`

## Lokaal testen (offline modus)

```bash
cd laravel
# Exporteer test database
php artisan offline:export --license

# Start in offline modus
set OFFLINE_MODE=true
set TOERNOOI_ID=6
php artisan serve --port=8000
```

## Bestandsstructuur

```
offline/
├── build.ps1           # Volledig geautomatiseerd build script
├── launcher/
│   ├── main.go         # Go launcher source
│   └── go.mod          # Go module
├── scripts/
│   └── build-offline-laravel.sh  # Linux alternatief
├── build/              # Build output (niet in git)
│   ├── launcher.exe
│   ├── php/
│   └── laravel/
├── _tools/             # Downloaded tools (niet in git)
│   └── go/
└── README.md
```

# JudoToernooi Offline Noodpakket

Standalone server die op een Windows laptop draait voor wedstrijddagen zonder internet.

## Architectuur

```
noodpakket_[toernooinaam].zip
├── noodpakket.exe          # Go launcher (~5MB)
├── bundle.zip              # Auto-extracted at runtime:
│   ├── php/                # Portable PHP 8.2 (~29MB)
│   ├── laravel/            # Stripped app (~30MB)
│   ├── database.sqlite     # Tournament data (~1MB)
│   └── license.json        # 3-day expiry, HMAC signed
└── LEES MIJ.txt
```

## Build Prerequisites

### 1. Go Compiler (voor launcher cross-compile)
```bash
# Linux/Mac
sudo apt install golang  # of brew install go

# Cross-compile voor Windows
cd offline/launcher
GOOS=windows GOARCH=amd64 go build -o ../build/launcher.exe
```

### 2. Portable PHP 8.2 for Windows
Download van https://windows.php.net/download/:
- **php-8.2.x-nts-Win32-vs16-x64.zip** (Non Thread Safe)
- Pak uit naar `offline/build/php/`
- Zorg dat deze extensions aanstaan in `php.ini`:
  - `extension=sqlite3`
  - `extension=pdo_sqlite`
  - `extension=mbstring`
  - `extension=openssl`
  - `extension=fileinfo`
  - `extension=curl` (optioneel, voor upload)

### 3. Stripped Laravel App
```bash
bash offline/scripts/build-offline-laravel.sh
```

## Build Structuur

Na het builden:
```
offline/build/
├── launcher.exe        # Go binary (Windows)
├── php/                # Portable PHP directory
│   ├── php.exe
│   ├── php.ini
│   └── ext/
└── laravel/            # Stripped Laravel app
    ├── artisan
    ├── app/
    ├── config/
    ├── routes/
    ├── resources/
    ├── vendor/
    └── ...
```

## Hoe het werkt

1. Organisator klikt "Download Server Pakket" op judotournament.org
2. Server genereert SQLite database met toernooi data
3. Server combineert: pre-built launcher + portable PHP + stripped Laravel + SQLite + license
4. Download als .zip (~65MB)
5. Organisator pakt uit en dubbelklikt `noodpakket.exe`
6. Launcher:
   - Checkt license (verlopen na 3 dagen)
   - Pakt `bundle.zip` uit naar `%TEMP%/noodpakket_judo/`
   - Schrijft `.env` met offline config
   - Start: `php artisan serve --host=0.0.0.0 --port=8000`
   - Opent browser op `http://localhost:8000`
   - Detecteert lokaal IP voor tablet verbinding

## Development

### Go launcher aanpassen
```bash
cd offline/launcher
# Lokaal testen (zonder embedded files)
go run main.go

# Build voor Windows
GOOS=windows GOARCH=amd64 go build -o ../build/launcher.exe
```

### Lokaal testen offline modus
```bash
cd laravel
# Maak test SQLite database
php artisan offline:export --toernooi=1

# Start in offline modus
OFFLINE_MODE=true TOERNOOI_ID=1 php artisan serve --port=8000
```

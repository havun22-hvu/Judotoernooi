# JudoToernooi Offline Noodpakket - Build Script
# Voert alles uit: Go installeren, PHP downloaden, Laravel strippen, launcher builden
#
# Gebruik: powershell -ExecutionPolicy Bypass -File offline\build.ps1

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
if (-not $ProjectRoot) { $ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path) }
$OfflineDir = Join-Path $ProjectRoot "offline"
$BuildDir = Join-Path $OfflineDir "build"
$LaravelSource = Join-Path $ProjectRoot "laravel"

# PHP versie en URL
$PhpVersion = "8.2.27"
$PhpUrl = "https://windows.php.net/downloads/releases/php-$PhpVersion-nts-Win32-vs16-x64.zip"
$PhpFallbackUrl = "https://windows.php.net/downloads/releases/archives/php-$PhpVersion-nts-Win32-vs16-x64.zip"

# Go versie en URL
$GoVersion = "1.22.5"
$GoUrl = "https://go.dev/dl/go$GoVersion.windows-amd64.zip"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  JudoToernooi Offline Noodpakket - Build" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# === Stap 0: Maak build directory ===
if (Test-Path $BuildDir) {
    Write-Host "[0/5] Build directory opschonen..." -ForegroundColor Yellow
    # Behoud .gitignore
    Get-ChildItem $BuildDir -Exclude ".gitignore" | Remove-Item -Recurse -Force
} else {
    New-Item -ItemType Directory -Path $BuildDir -Force | Out-Null
}

# === Stap 1: Go installeren (als niet aanwezig) ===
Write-Host "[1/5] Go compiler checken..." -ForegroundColor Green

$GoExe = $null
$GoInstalled = Get-Command go -ErrorAction SilentlyContinue
if ($GoInstalled) {
    $GoExe = $GoInstalled.Source
    Write-Host "      Go gevonden: $GoExe" -ForegroundColor Gray
} else {
    # Download portable Go
    $GoDir = Join-Path $OfflineDir "_tools\go"
    $GoExe = Join-Path $GoDir "bin\go.exe"

    if (-not (Test-Path $GoExe)) {
        Write-Host "      Go niet gevonden, downloaden ($GoVersion)..." -ForegroundColor Yellow
        $GoZip = Join-Path $env:TEMP "go-$GoVersion.zip"

        if (-not (Test-Path $GoZip)) {
            Invoke-WebRequest -Uri $GoUrl -OutFile $GoZip -UseBasicParsing
        }

        $GoToolsDir = Join-Path $OfflineDir "_tools"
        New-Item -ItemType Directory -Path $GoToolsDir -Force | Out-Null

        Write-Host "      Go uitpakken..." -ForegroundColor Yellow
        Expand-Archive -Path $GoZip -DestinationPath $GoToolsDir -Force

        if (-not (Test-Path $GoExe)) {
            Write-Host "[FOUT] Go installatie mislukt!" -ForegroundColor Red
            exit 1
        }
    }
    Write-Host "      Go portable: $GoExe" -ForegroundColor Gray
}

# Verify Go works
$GoVersionOutput = & $GoExe version 2>&1
Write-Host "      $GoVersionOutput" -ForegroundColor Gray

# === Stap 2: Portable PHP downloaden ===
Write-Host "[2/5] Portable PHP downloaden..." -ForegroundColor Green

$PhpDir = Join-Path $BuildDir "php"
$PhpExe = Join-Path $PhpDir "php.exe"

if (Test-Path $PhpExe) {
    Write-Host "      PHP al aanwezig, overslaan" -ForegroundColor Gray
} else {
    $PhpZip = Join-Path $env:TEMP "php-$PhpVersion-nts.zip"

    if (-not (Test-Path $PhpZip)) {
        Write-Host "      Downloaden van windows.php.net ($PhpVersion)..." -ForegroundColor Yellow
        try {
            Invoke-WebRequest -Uri $PhpUrl -OutFile $PhpZip -UseBasicParsing
        } catch {
            Write-Host "      Primaire URL mislukt, probeer archives..." -ForegroundColor Yellow
            try {
                Invoke-WebRequest -Uri $PhpFallbackUrl -OutFile $PhpZip -UseBasicParsing
            } catch {
                Write-Host "[FOUT] Kan PHP niet downloaden!" -ForegroundColor Red
                Write-Host "       Download handmatig van https://windows.php.net/download/" -ForegroundColor Red
                Write-Host "       Pak uit naar: $PhpDir" -ForegroundColor Red
                exit 1
            }
        }
    }

    Write-Host "      PHP uitpakken naar $PhpDir..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $PhpDir -Force | Out-Null
    Expand-Archive -Path $PhpZip -DestinationPath $PhpDir -Force

    # Configureer php.ini
    $PhpIniSource = Join-Path $PhpDir "php.ini-production"
    $PhpIni = Join-Path $PhpDir "php.ini"

    if (Test-Path $PhpIniSource) {
        $IniContent = Get-Content $PhpIniSource -Raw

        # Activeer benodigde extensions
        $IniContent = $IniContent -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
        $IniContent = $IniContent -replace ';extension=curl', 'extension=curl'
        $IniContent = $IniContent -replace ';extension=fileinfo', 'extension=fileinfo'
        $IniContent = $IniContent -replace ';extension=mbstring', 'extension=mbstring'
        $IniContent = $IniContent -replace ';extension=openssl', 'extension=openssl'
        $IniContent = $IniContent -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite'
        $IniContent = $IniContent -replace ';extension=sqlite3', 'extension=sqlite3'

        Set-Content -Path $PhpIni -Value $IniContent
        Write-Host "      php.ini geconfigureerd met sqlite/mbstring/openssl extensions" -ForegroundColor Gray
    }
}

# Verify PHP works
$PhpVersionOutput = & $PhpExe -v 2>&1 | Select-Object -First 1
Write-Host "      $PhpVersionOutput" -ForegroundColor Gray

# === Stap 3: Laravel app strippen ===
Write-Host "[3/5] Laravel app strippen (wedstrijddag-only)..." -ForegroundColor Green

$LaravelTarget = Join-Path $BuildDir "laravel"
if (Test-Path $LaravelTarget) {
    Remove-Item -Recurse -Force $LaravelTarget
}

# Kopieer essentials
$CopyDirs = @("app", "bootstrap", "config", "database", "public", "routes", "storage", "vendor", "resources")
New-Item -ItemType Directory -Path $LaravelTarget -Force | Out-Null

foreach ($dir in $CopyDirs) {
    $src = Join-Path $LaravelSource $dir
    $dst = Join-Path $LaravelTarget $dir
    if (Test-Path $src) {
        Write-Host "      Kopieer $dir..." -ForegroundColor Gray
        Copy-Item -Path $src -Destination $dst -Recurse -Force
    }
}

# Kopieer root bestanden
Copy-Item (Join-Path $LaravelSource "artisan") $LaravelTarget
if (Test-Path (Join-Path $LaravelSource "composer.json")) {
    Copy-Item (Join-Path $LaravelSource "composer.json") $LaravelTarget
}

# Verwijder onnodige bestanden
$RemoveFiles = @(
    "app\Http\Controllers\AdminController.php",
    "app\Http\Controllers\OrganisatorAuthController.php",
    "app\Http\Controllers\MollieController.php",
    "app\Http\Controllers\ToernooiBetalingController.php",
    "app\Http\Controllers\PaginaBuilderController.php",
    "app\Http\Controllers\GewichtsklassenPresetController.php",
    "app\Services\ImportService.php",
    "app\Services\PouleIndelingService.php",
    "app\Services\MollieService.php",
    "app\Services\OfflinePackageBuilder.php",
    "app\Services\OfflineExportService.php"
)

foreach ($file in $RemoveFiles) {
    $path = Join-Path $LaravelTarget $file
    if (Test-Path $path) { Remove-Item $path -Force }
}

# Verwijder directories
$RemoveDirs = @("tests", "docs", "node_modules", ".git", "database\migrations")
foreach ($dir in $RemoveDirs) {
    $path = Join-Path $LaravelTarget $dir
    if (Test-Path $path) { Remove-Item -Recurse -Force $path }
}

# Verwijder Exports directory
$exportsDir = Join-Path $LaravelTarget "app\Exports"
if (Test-Path $exportsDir) { Remove-Item -Recurse -Force $exportsDir }

# Maak lege migrations dir (Laravel verwacht dit)
New-Item -ItemType Directory -Path (Join-Path $LaravelTarget "database\migrations") -Force | Out-Null

# Schoon storage op
$cleanDirs = @("storage\logs", "storage\framework\cache\data", "storage\framework\sessions", "storage\framework\views")
foreach ($dir in $cleanDirs) {
    $path = Join-Path $LaravelTarget $dir
    if (Test-Path $path) {
        Get-ChildItem $path -File | Remove-Item -Force
    } else {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

# Zorg dat bootstrap\cache bestaat
New-Item -ItemType Directory -Path (Join-Path $LaravelTarget "bootstrap\cache") -Force | Out-Null

# Bereken grootte
$LaravelSize = [math]::Round(((Get-ChildItem -Recurse $LaravelTarget | Measure-Object -Property Length -Sum).Sum / 1MB), 1)
Write-Host "      Laravel gestript: ${LaravelSize}MB" -ForegroundColor Gray

# === Stap 4: Go launcher builden ===
Write-Host "[4/5] Go launcher compileren..." -ForegroundColor Green

$LauncherDir = Join-Path $OfflineDir "launcher"
$LauncherExe = Join-Path $BuildDir "launcher.exe"

# Build Go binary
$env:CGO_ENABLED = "0"
Push-Location $LauncherDir
& $GoExe build -ldflags="-s -w" -o $LauncherExe .
$buildResult = $LASTEXITCODE
Pop-Location

if ($buildResult -ne 0) {
    Write-Host "[FOUT] Go build mislukt!" -ForegroundColor Red
    exit 1
}

$LauncherSize = [math]::Round((Get-Item $LauncherExe).Length / 1MB, 1)
Write-Host "      launcher.exe: ${LauncherSize}MB" -ForegroundColor Gray

# === Stap 5: Samenvatting ===
Write-Host ""
Write-Host "[5/5] Build compleet!" -ForegroundColor Green
Write-Host ""

$PhpSize = [math]::Round(((Get-ChildItem -Recurse $PhpDir | Measure-Object -Property Length -Sum).Sum / 1MB), 1)
$TotalSize = [math]::Round($LauncherSize + $PhpSize + $LaravelSize, 1)

Write-Host "  Onderdeel        Grootte" -ForegroundColor Cyan
Write-Host "  ─────────────────────────" -ForegroundColor Gray
Write-Host "  launcher.exe     ${LauncherSize}MB"
Write-Host "  php/             ${PhpSize}MB"
Write-Host "  laravel/         ${LaravelSize}MB"
Write-Host "  ─────────────────────────" -ForegroundColor Gray
Write-Host "  Totaal (excl db) ${TotalSize}MB"
Write-Host ""
Write-Host "  Build output: $BuildDir" -ForegroundColor Gray
Write-Host ""
Write-Host "  De server download op judotournament.org combineert dit" -ForegroundColor Yellow
Write-Host "  met de SQLite database en license per toernooi." -ForegroundColor Yellow
Write-Host ""

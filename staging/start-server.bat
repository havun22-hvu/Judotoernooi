@echo off
title JudoToernooi Lokale Server
color 0A

echo.
echo ========================================
echo   JudoToernooi Lokale Server
echo ========================================
echo.

REM Check if PHP is available
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [!] PHP niet gevonden.
    echo.
    echo Laravel Herd wordt nu gedownload...
    echo.

    REM Open Herd download page
    start https://herd.laravel.com/windows

    echo ========================================
    echo   INSTALLATIE INSTRUCTIES:
    echo ========================================
    echo.
    echo   1. Download Laravel Herd van de website
    echo   2. Installeer Herd (dubbelklik installer)
    echo   3. Herstart deze computer
    echo   4. Dubbelklik opnieuw op start-server.bat
    echo.
    echo ========================================
    echo.
    pause
    exit /b 1
)

echo [OK] PHP gevonden:
php -v | findstr /R "^PHP"
echo.

REM Change to Laravel directory
cd /d "%~dp0"

REM Check if .env exists
if not exist ".env" (
    echo [!] .env bestand niet gevonden, kopieren van .env.example...
    copy .env.example .env >nul
    php artisan key:generate --quiet
)

echo ========================================
echo   Server wordt gestart...
echo ========================================
echo.
echo   Dashboard:  http://127.0.0.1:8000/local-server
echo   Setup:      http://127.0.0.1:8000/local-server/setup
echo.
echo   Druk Ctrl+C om te stoppen
echo ========================================
echo.

REM Open browser automatically
start http://127.0.0.1:8000/local-server

REM Start server
php artisan serve --port=8000

pause

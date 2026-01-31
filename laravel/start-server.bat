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
    echo [FOUT] PHP niet gevonden!
    echo.
    echo Installeer Laravel Herd: https://herd.laravel.com/windows
    echo Of installeer PHP handmatig.
    echo.
    pause
    exit /b 1
)

echo PHP gevonden:
php -v | findstr /R "^PHP"
echo.

REM Change to Laravel directory
cd /d "%~dp0"

echo Starting server...
echo.
echo ========================================
echo   Server actief op http://127.0.0.1:8000
echo   Lokaal dashboard: http://127.0.0.1:8000/local-server
echo ========================================
echo.
echo Druk Ctrl+C om te stoppen
echo.

php artisan serve --port=8000

pause

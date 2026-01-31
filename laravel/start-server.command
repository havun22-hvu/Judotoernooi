#!/bin/bash

echo ""
echo "========================================"
echo "  JudoToernooi Lokale Server"
echo "========================================"
echo ""

# Change to script directory
cd "$(dirname "$0")"

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "[!] PHP niet gevonden."
    echo ""
    echo "Laravel Herd wordt nu geopend..."
    echo ""

    # Open Herd download page
    open "https://herd.laravel.com"

    echo "========================================"
    echo "  INSTALLATIE INSTRUCTIES:"
    echo "========================================"
    echo ""
    echo "  1. Download Laravel Herd van de website"
    echo "  2. Sleep Herd naar Applications"
    echo "  3. Open Herd vanuit Applications"
    echo "  4. Dubbelklik opnieuw op start-server.command"
    echo ""
    echo "========================================"
    echo ""
    read -p "Druk op Enter om te sluiten..."
    exit 1
fi

echo "[OK] PHP gevonden:"
php -v | head -1
echo ""

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "[!] .env bestand niet gevonden, kopieren van .env.example..."
    cp .env.example .env
    php artisan key:generate --quiet
fi

echo "========================================"
echo "  Server wordt gestart..."
echo "========================================"
echo ""
echo "  Dashboard:  http://127.0.0.1:8000/local-server"
echo "  Setup:      http://127.0.0.1:8000/local-server/setup"
echo ""
echo "  Druk Ctrl+C om te stoppen"
echo "========================================"
echo ""

# Open browser automatically
open "http://127.0.0.1:8000/local-server"

# Start server
php artisan serve --port=8000

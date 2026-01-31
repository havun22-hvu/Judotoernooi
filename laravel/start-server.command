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
    echo "[FOUT] PHP niet gevonden!"
    echo ""
    echo "Installeer Laravel Herd: https://herd.laravel.com"
    echo "Of installeer PHP via Homebrew: brew install php"
    echo ""
    read -p "Druk op Enter om te sluiten..."
    exit 1
fi

echo "PHP gevonden:"
php -v | head -1
echo ""

echo "Starting server..."
echo ""
echo "========================================"
echo "  Server actief op http://127.0.0.1:8000"
echo "  Lokaal dashboard: http://127.0.0.1:8000/local-server"
echo "========================================"
echo ""
echo "Druk Ctrl+C om te stoppen"
echo ""

php artisan serve --port=8000

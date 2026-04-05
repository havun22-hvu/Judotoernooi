#!/bin/bash
# Post-deploy script for JudoToernooi
# Run after git pull on production/staging servers
# Also used as git post-merge hook

set -e

# Find Laravel root
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -d "$SCRIPT_DIR/../laravel" ]; then
    LARAVEL_DIR="$SCRIPT_DIR/../laravel"
elif [ -f "$SCRIPT_DIR/artisan" ]; then
    LARAVEL_DIR="$SCRIPT_DIR"
else
    LARAVEL_DIR="$(git rev-parse --show-toplevel 2>/dev/null)/laravel"
fi

cd "$LARAVEL_DIR"

echo "[post-deploy] Clearing and rebuilding caches..."
php artisan optimize:clear 2>/dev/null
php artisan optimize 2>/dev/null
echo "[post-deploy] Laravel caches cleared and optimized"

echo ""
echo "[post-deploy] Running Reverb health check..."
if php artisan reverb:health --fix 2>/dev/null; then
    echo "[post-deploy] ✓ Reverb healthy"
else
    echo ""
    echo "╔══════════════════════════════════════════════════════╗"
    echo "║  ⚠️  REVERB HEALTH CHECK FAILED                     ║"
    echo "║  Broadcasting is broken — LCD displays will not      ║"
    echo "║  receive events. Check the output above.             ║"
    echo "╚══════════════════════════════════════════════════════╝"
    echo ""
    # Don't fail the deploy — but make it very visible
fi

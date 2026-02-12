#!/bin/bash
# Build script: creates a stripped "wedstrijddag-only" copy of the Laravel app
# Run from the project root: bash offline/scripts/build-offline-laravel.sh

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SOURCE="$PROJECT_ROOT/laravel"
TARGET="$PROJECT_ROOT/offline/build/laravel"

echo "=== Building offline Laravel app ==="
echo "Source: $SOURCE"
echo "Target: $TARGET"

# Clean previous build
rm -rf "$TARGET"
mkdir -p "$TARGET"

# ---- Copy core Laravel structure ----
echo "[1/6] Copying core framework files..."

# Essential directories
for dir in app bootstrap config database public routes storage; do
    cp -r "$SOURCE/$dir" "$TARGET/$dir"
done

# Essential root files
cp "$SOURCE/artisan" "$TARGET/"
cp "$SOURCE/composer.json" "$TARGET/"
cp "$SOURCE/composer.lock" "$TARGET/"

# Vendor directory (all dependencies)
echo "[2/6] Copying vendor dependencies..."
cp -r "$SOURCE/vendor" "$TARGET/vendor"

# ---- Copy resources (views, JS, CSS) ----
echo "[3/6] Copying resources..."
cp -r "$SOURCE/resources" "$TARGET/resources"

# Copy built assets
if [ -d "$SOURCE/public/build" ]; then
    cp -r "$SOURCE/public/build" "$TARGET/public/build"
fi

# ---- Remove unnecessary code for offline ----
echo "[4/6] Stripping unnecessary features..."

# Remove admin/organisator management controllers
rm -f "$TARGET/app/Http/Controllers/AdminController.php"
rm -f "$TARGET/app/Http/Controllers/OrganisatorAuthController.php"
rm -f "$TARGET/app/Http/Controllers/MollieController.php"
rm -f "$TARGET/app/Http/Controllers/ToernooiBetalingController.php"
rm -f "$TARGET/app/Http/Controllers/PaginaBuilderController.php"
rm -f "$TARGET/app/Http/Controllers/GewichtsklassenPresetController.php"

# Remove import/classification services (not needed on wedstrijddag)
rm -f "$TARGET/app/Services/ImportService.php"
rm -f "$TARGET/app/Services/PouleIndelingService.php"
rm -f "$TARGET/app/Services/MollieService.php"
rm -f "$TARGET/app/Services/OfflinePackageBuilder.php"
rm -f "$TARGET/app/Services/OfflineExportService.php"

# Remove exports
rm -rf "$TARGET/app/Exports"

# Remove database migrations (schema is in SQLite already)
rm -rf "$TARGET/database/migrations"
mkdir -p "$TARGET/database/migrations"

# Remove tests
rm -rf "$TARGET/tests"

# Remove docs
rm -rf "$TARGET/docs"

# Remove node_modules if present
rm -rf "$TARGET/node_modules"

# Remove git files
rm -rf "$TARGET/.git"
rm -f "$TARGET/.gitignore"
rm -f "$TARGET/.gitattributes"

# Clean storage (keep structure)
rm -rf "$TARGET/storage/logs/*"
rm -rf "$TARGET/storage/framework/cache/data/*"
rm -rf "$TARGET/storage/framework/sessions/*"
rm -rf "$TARGET/storage/framework/views/*"

# Ensure writable directories exist
mkdir -p "$TARGET/storage/logs"
mkdir -p "$TARGET/storage/framework/cache/data"
mkdir -p "$TARGET/storage/framework/sessions"
mkdir -p "$TARGET/storage/framework/views"
mkdir -p "$TARGET/storage/app"
mkdir -p "$TARGET/bootstrap/cache"

# ---- Create offline-specific .env template ----
echo "[5/6] Creating offline config..."

cat > "$TARGET/.env.offline" << 'EOF'
APP_NAME=JudoToernooi
APP_ENV=offline
APP_DEBUG=false
APP_URL=http://localhost:8000

OFFLINE_MODE=true

DB_CONNECTION=sqlite

LOG_CHANNEL=single
LOG_LEVEL=warning

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
EOF

# ---- Summary ----
echo "[6/6] Build complete!"
echo ""
echo "Output: $TARGET"
echo "Size: $(du -sh "$TARGET" | cut -f1)"
echo ""
echo "Next steps:"
echo "  1. Download portable PHP 8.2 to offline/build/php/"
echo "  2. Cross-compile Go launcher: cd offline/launcher && GOOS=windows GOARCH=amd64 go build -o ../build/launcher.exe"
echo "  3. The server download endpoint will combine everything into a .zip"

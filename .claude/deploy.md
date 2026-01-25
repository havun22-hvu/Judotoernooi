# Deploy Instructies

## Local Development

```bash
cd laravel
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8007
```

## Deploy (Staging)

```bash
ssh root@188.245.159.115
cd /var/www/staging.judotoernooi/laravel

git pull origin master
composer install --no-dev
php artisan migrate

# ALTIJD caches clearen VOOR opnieuw opbouwen!
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Daarna opnieuw cachen
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Deploy (Production)

```bash
ssh root@188.245.159.115
cd /var/www/judotoernooi/laravel

git pull origin master
composer install --no-dev
php artisan migrate

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Let op:** Zonder cache:clear kunnen gebruikers in redirect loop komen bij login!

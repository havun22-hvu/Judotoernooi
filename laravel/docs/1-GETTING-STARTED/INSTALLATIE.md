# Installatie Handleiding

## Systeemvereisten

- PHP 8.2 of hoger
- Composer 2.x
- MySQL 8.0+ of MariaDB 10.4+
- Node.js 18+ en NPM (voor frontend assets)
- Git

### PHP Extensions

- BCMath
- Ctype
- cURL
- DOM
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- ZIP

## Installatie Stappen

### 1. Clone de Repository

```bash
git clone https://github.com/judoschool-cees-veen/judo-toernooi.git
cd judo-toernooi/laravel
```

### 2. Installeer PHP Dependencies

```bash
composer install
```

### 3. Installeer Node Dependencies

```bash
npm install
```

### 4. Configuratie Bestand

```bash
cp .env.example .env
```

Open `.env` en pas de volgende waarden aan:

```env
APP_NAME="WestFries Open JudoToernooi"
APP_URL=https://judotournament.org

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=judo_toernooi
DB_USERNAME=jouw_db_user
DB_PASSWORD=jouw_db_wachtwoord

ADMIN_PASSWORD=VeiligWachtwoord123
```

### 5. Genereer Application Key

```bash
php artisan key:generate
```

### 6. Maak Database

```sql
CREATE DATABASE judo_toernooi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 7. Run Migraties

```bash
php artisan migrate
```

### 8. (Optioneel) Seed Test Data

```bash
php artisan db:seed
```

### 9. Build Frontend Assets

```bash
npm run build
```

### 10. Configureer Webserver

#### Apache (.htaccess)

De standaard Laravel `.htaccess` in `public/` zou moeten werken.

#### Nginx

```nginx
server {
    listen 80;
    server_name judotournament.org;
    root /var/www/judotoernooi/laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 11. Stel Permissies In

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Verificatie

Bezoek de URL en je zou het dashboard moeten zien.

## Troubleshooting

### "Permission denied" errors

```bash
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Database connection error

Controleer of MySQL draait en de credentials correct zijn:

```bash
mysql -u jouw_db_user -p -e "SELECT 1"
```

### Composer memory error

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

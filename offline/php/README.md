# Portable PHP voor Windows

Download PHP 8.2 NTS (Non Thread Safe) voor Windows x64:
https://windows.php.net/download/

1. Download `php-8.2.x-nts-Win32-vs16-x64.zip`
2. Pak uit naar `offline/build/php/`
3. Kopieer `php.ini-production` naar `php.ini`
4. Activeer in php.ini:

```ini
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
```

Test: `php/php.exe -v`

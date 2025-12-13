# Einde sessie - Opruimen en deployen

Voer de volgende stappen uit om de sessie netjes af te sluiten:

## 1. Documentatie bijwerken
- Werk relevante .md files bij in `laravel/docs/` als er wijzigingen zijn gedaan
- Update PLANNING_AUTHENTICATIE_SYSTEEM.md als auth gerelateerd
- Update andere docs indien nodig

## 2. Git commit en push
- Stage alle wijzigingen
- Maak een commit met duidelijke Engelse message
- Push naar GitHub

## 3. Deploy naar server
- SSH naar 188.245.159.115
- `cd /var/www/judotoernooi/laravel`
- `git pull`
- `composer install --no-dev` (alleen als composer.json gewijzigd)
- `php artisan migrate` (alleen als migrations toegevoegd)
- `php artisan config:clear && php artisan cache:clear`

## 4. Branches opruimen
- Verwijder gemergte lokale branches: `git branch --merged main | grep -v main | xargs git branch -d`
- Verwijder gemergte remote branches: `git fetch --prune`
- Sluit afgeronde PRs op GitHub

## 5. Bevestig aan gebruiker
- Geef korte samenvatting wat gedaan is
- Meld deploy status
- Tot ziens!

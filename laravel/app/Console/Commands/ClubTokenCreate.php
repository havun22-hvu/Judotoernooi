<?php

namespace App\Console\Commands;

use App\Models\ClubApiToken;
use App\Models\Organisator;
use Illuminate\Console\Command;

/**
 * Issue a HavunClub API token for an Organisator.
 *
 * The plaintext token is printed exactly once — store it in HavunClub's
 * /koppelingen config. Mirrors how scoreboard device tokens are handed out.
 */
class ClubTokenCreate extends Command
{
    protected $signature = 'club:token-create {organisator : Organisator id or slug} {--label=HavunClub}';

    protected $description = 'Maak een HavunClub API-token aan voor een organisator';

    public function handle(): int
    {
        $key = $this->argument('organisator');
        $organisator = Organisator::where('id', $key)
            ->orWhere('slug', $key)
            ->first();

        if (!$organisator) {
            $this->error("Organisator '{$key}' niet gevonden (zoek op id of slug).");

            return self::FAILURE;
        }

        $plain = ClubApiToken::generateToken();
        ClubApiToken::create([
            'organisator_id' => $organisator->id,
            'token' => $plain,
            'label' => $this->option('label'),
            'actief' => true,
        ]);

        $this->info("Token aangemaakt voor: {$organisator->organisatie_naam} (#{$organisator->id})");
        $this->line('Bewaar dit token (wordt maar één keer getoond):');
        $this->line($plain);

        return self::SUCCESS;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vrijwilliger extends Model
{
    protected $table = 'vrijwilligers';

    protected $fillable = [
        'organisator_id',
        'voornaam',
        'telefoonnummer',
        'email',
        'functie',
    ];

    public const FUNCTIES = ['mat', 'weging', 'spreker', 'dojo', 'hoofdjury'];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function getFunctieLabel(): string
    {
        return ucfirst($this->functie);
    }

    public function getWhatsAppUrl(string $bericht): string
    {
        if (empty($this->telefoonnummer)) {
            return '';
        }

        // Clean phone number: remove spaces, dashes, keep + for country code
        $nummer = preg_replace('/[^0-9+]/', '', $this->telefoonnummer);

        // Convert Dutch format (06-xxx) to international (+316xxx)
        if (str_starts_with($nummer, '06')) {
            $nummer = '+31' . substr($nummer, 1);
        } elseif (str_starts_with($nummer, '0')) {
            $nummer = '+31' . substr($nummer, 1);
        }

        return 'https://wa.me/' . ltrim($nummer, '+') . '?text=' . urlencode($bericht);
    }
}

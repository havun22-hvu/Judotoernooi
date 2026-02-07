<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'toernooi_id',
        'actie',
        'model_type',
        'model_id',
        'beschrijving',
        'properties',
        'actor_type',
        'actor_id',
        'actor_naam',
        'ip_adres',
        'interface',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function scopeVoorActie($query, string $actie)
    {
        return $query->where('actie', $actie);
    }

    public function scopeVoorModel($query, string $modelType, ?int $modelId = null)
    {
        $query->where('model_type', $modelType);
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        return $query;
    }

    /**
     * Get readable action name
     */
    public function getActieNaamAttribute(): string
    {
        return match ($this->actie) {
            'verplaats_judoka' => 'Verplaats judoka',
            'nieuwe_judoka' => 'Nieuwe judoka',
            'meld_af' => 'Afmelding',
            'herstel_judoka' => 'Herstel judoka',
            'naar_wachtruimte' => 'Naar wachtruimte',
            'verwijder_uit_poule' => 'Verwijder uit poule',
            'registreer_uitslag' => 'Uitslag geregistreerd',
            'plaats_judoka' => 'Judoka geplaatst',
            'verwijder_judoka' => 'Judoka verwijderd',
            'poule_klaar' => 'Poule klaar',
            'registreer_gewicht' => 'Gewicht geregistreerd',
            'markeer_aanwezig' => 'Aanwezig gemarkeerd',
            'markeer_afwezig' => 'Afwezig gemarkeerd',
            'genereer_poules' => 'Poules gegenereerd',
            'maak_poule' => 'Poule aangemaakt',
            'verwijder_poule' => 'Poule verwijderd',
            'sluit_weging' => 'Weging gesloten',
            'activeer_categorie' => 'Categorie geactiveerd',
            'reset_categorie' => 'Categorie gereset',
            'reset_alles' => 'Alles gereset',
            'reset_blok' => 'Blok gereset',
            'update_instellingen' => 'Instellingen bijgewerkt',
            'afsluiten' => 'Toernooi afgesloten',
            'verwijder_toernooi' => 'Toernooi verwijderd',
            default => ucfirst(str_replace('_', ' ', $this->actie)),
        };
    }
}

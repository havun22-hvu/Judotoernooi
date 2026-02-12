<?php

namespace App\Services;

use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Exports tournament data to a standalone SQLite database for offline use.
 * Used by the noodpakket download feature.
 */
class OfflineExportService
{
    private ?\PDO $sqlite = null;
    private string $dbPath;

    /**
     * Export a tournament to a standalone SQLite database file.
     *
     * @return string Path to the generated SQLite file
     */
    public function export(Toernooi $toernooi): string
    {
        $this->dbPath = storage_path('app/offline_' . $toernooi->id . '_' . time() . '.sqlite');

        // Create fresh SQLite database
        $this->sqlite = new \PDO('sqlite:' . $this->dbPath);
        $this->sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->sqlite->exec('PRAGMA journal_mode=WAL');
        // Disable FK constraints during data import, enable after
        $this->sqlite->exec('PRAGMA foreign_keys=OFF');

        try {
            $this->createSchema();
            $this->exportToernooi($toernooi);
            $this->exportClubs($toernooi);
            $this->exportJudokas($toernooi);
            $this->exportBlokken($toernooi);
            $this->exportMatten($toernooi);
            $this->exportPoules($toernooi);
            $this->exportPouleJudoka($toernooi);
            $this->exportWedstrijden($toernooi);
            $this->exportDeviceToegangen($toernooi);
            $this->exportCoachKaarten($toernooi);
            $this->createOfflineMeta($toernooi);

            // Re-enable FK constraints for runtime use
            $this->sqlite->exec('PRAGMA foreign_keys=ON');
        } catch (\Exception $e) {
            // Close PDO connection before cleanup
            $this->sqlite = null;
            if (file_exists($this->dbPath)) {
                unlink($this->dbPath);
            }
            throw $e;
        }

        // Close PDO connection so file can be moved/deleted
        $this->sqlite = null;

        return $this->dbPath;
    }

    /**
     * Generate a signed license for offline use.
     */
    public function generateLicense(Toernooi $toernooi, int $validDays = 3): array
    {
        $license = [
            'toernooi_id' => $toernooi->id,
            'toernooi_naam' => $toernooi->naam,
            'organisator' => $toernooi->organisator?->naam ?? 'Onbekend',
            'generated_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays($validDays)->toIso8601String(),
            'valid_days' => $validDays,
        ];

        $license['signature'] = hash_hmac('sha256', json_encode([
            $license['toernooi_id'],
            $license['generated_at'],
            $license['expires_at'],
        ]), config('app.key'));

        return $license;
    }

    /**
     * Verify a license signature and expiration.
     */
    public static function verifyLicense(array $license): bool
    {
        if (!isset($license['signature'], $license['toernooi_id'], $license['generated_at'], $license['expires_at'])) {
            return false;
        }

        // Check expiration
        if (now()->isAfter($license['expires_at'])) {
            return false;
        }

        // Check signature
        $expected = hash_hmac('sha256', json_encode([
            $license['toernooi_id'],
            $license['generated_at'],
            $license['expires_at'],
        ]), config('app.key'));

        return hash_equals($expected, $license['signature']);
    }

    private function createSchema(): void
    {
        $this->sqlite->exec('
            CREATE TABLE toernooien (
                id INTEGER PRIMARY KEY,
                naam TEXT NOT NULL,
                slug TEXT,
                organisatie TEXT,
                datum TEXT,
                locatie TEXT,
                aantal_matten INTEGER DEFAULT 1,
                aantal_blokken INTEGER DEFAULT 1,
                wedstrijd_systeem TEXT DEFAULT "poule",
                eliminatie_type TEXT,
                aantal_brons INTEGER DEFAULT 1,
                gewicht_tolerantie REAL DEFAULT 0,
                gebruik_gewichtsklassen INTEGER DEFAULT 1,
                wedstrijd_schemas TEXT,
                gewichtsklassen TEXT,
                code_hoofdjury TEXT,
                code_weging TEXT,
                code_mat TEXT,
                code_spreker TEXT,
                code_dojo TEXT,
                wachtwoord_admin TEXT,
                wachtwoord_jury TEXT,
                wachtwoord_weging TEXT,
                wachtwoord_mat TEXT,
                wachtwoord_spreker TEXT,
                is_actief INTEGER DEFAULT 1,
                thema_kleur TEXT,
                locale TEXT DEFAULT "nl",
                spreker_notities TEXT,
                best_of_three_bij_2 INTEGER DEFAULT 0,
                eliminatie_gewichtsklassen TEXT,
                kruisfinales_aantal INTEGER DEFAULT 0
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                naam TEXT NOT NULL,
                afkorting TEXT,
                plaats TEXT,
                contact_naam TEXT,
                contact_email TEXT,
                contact_telefoon TEXT
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE blokken (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                nummer INTEGER NOT NULL,
                starttijd TEXT,
                eindtijd TEXT,
                weging_gesloten INTEGER DEFAULT 0,
                blok_label TEXT,
                weging_start_tijd TEXT,
                weging_stop_tijd TEXT,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE matten (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                nummer INTEGER NOT NULL,
                naam TEXT,
                kleur TEXT,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE judokas (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                club_id INTEGER,
                naam TEXT NOT NULL,
                voornaam TEXT,
                achternaam TEXT,
                geboortejaar INTEGER,
                geslacht TEXT,
                band TEXT,
                gewicht REAL,
                leeftijdsklasse TEXT,
                gewichtsklasse TEXT,
                judoka_code TEXT,
                aanwezigheid TEXT DEFAULT "onbekend",
                gewicht_gewogen REAL,
                opmerking TEXT,
                qr_code TEXT,
                categorie_key TEXT,
                sort_categorie INTEGER DEFAULT 0,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id),
                FOREIGN KEY (club_id) REFERENCES clubs(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE poules (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                blok_id INTEGER,
                mat_id INTEGER,
                nummer INTEGER NOT NULL,
                titel TEXT,
                leeftijdsklasse TEXT,
                gewichtsklasse TEXT,
                type TEXT DEFAULT "poule",
                aantal_judokas INTEGER DEFAULT 0,
                aantal_wedstrijden INTEGER DEFAULT 0,
                vast INTEGER DEFAULT 0,
                categorie_key TEXT,
                spreker_klaar INTEGER DEFAULT 0,
                doorgestuurd_op TEXT,
                afgeroepen_at TEXT,
                huidige_wedstrijd_id INTEGER,
                actieve_wedstrijd_id INTEGER,
                barrage_van_poule_id INTEGER,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id),
                FOREIGN KEY (blok_id) REFERENCES blokken(id),
                FOREIGN KEY (mat_id) REFERENCES matten(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE poule_judoka (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poule_id INTEGER NOT NULL,
                judoka_id INTEGER NOT NULL,
                positie INTEGER DEFAULT 0,
                punten INTEGER DEFAULT 0,
                gewonnen INTEGER DEFAULT 0,
                verloren INTEGER DEFAULT 0,
                gelijk INTEGER DEFAULT 0,
                eindpositie INTEGER,
                FOREIGN KEY (poule_id) REFERENCES poules(id),
                FOREIGN KEY (judoka_id) REFERENCES judokas(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE wedstrijden (
                id INTEGER PRIMARY KEY,
                poule_id INTEGER NOT NULL,
                judoka_wit_id INTEGER,
                judoka_blauw_id INTEGER,
                volgorde INTEGER DEFAULT 0,
                winnaar_id INTEGER,
                score_wit INTEGER DEFAULT 0,
                score_blauw INTEGER DEFAULT 0,
                uitslag_type TEXT,
                is_gespeeld INTEGER DEFAULT 0,
                gespeeld_op TEXT,
                ronde TEXT,
                potje_nummer INTEGER,
                poule_volgorde INTEGER,
                FOREIGN KEY (poule_id) REFERENCES poules(id),
                FOREIGN KEY (judoka_wit_id) REFERENCES judokas(id),
                FOREIGN KEY (judoka_blauw_id) REFERENCES judokas(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE device_toegangen (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                rol TEXT NOT NULL,
                mat_nummer INTEGER,
                code TEXT,
                pincode TEXT,
                device_token TEXT,
                device_info TEXT,
                vrijwilliger_naam TEXT,
                vrijwilliger_email TEXT,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id)
            )
        ');

        $this->sqlite->exec('
            CREATE TABLE coach_kaarten (
                id INTEGER PRIMARY KEY,
                toernooi_id INTEGER NOT NULL,
                club_id INTEGER,
                naam TEXT,
                qr_code TEXT,
                is_gescand INTEGER DEFAULT 0,
                pincode TEXT,
                FOREIGN KEY (toernooi_id) REFERENCES toernooien(id),
                FOREIGN KEY (club_id) REFERENCES clubs(id)
            )
        ');

        // Offline-specific metadata table
        $this->sqlite->exec('
            CREATE TABLE offline_meta (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ');

        // Indexes for performance
        $this->sqlite->exec('CREATE INDEX idx_judokas_toernooi ON judokas(toernooi_id)');
        $this->sqlite->exec('CREATE INDEX idx_judokas_club ON judokas(club_id)');
        $this->sqlite->exec('CREATE INDEX idx_poules_toernooi ON poules(toernooi_id)');
        $this->sqlite->exec('CREATE INDEX idx_poules_mat ON poules(mat_id)');
        $this->sqlite->exec('CREATE INDEX idx_poules_blok ON poules(blok_id)');
        $this->sqlite->exec('CREATE INDEX idx_wedstrijden_poule ON wedstrijden(poule_id)');
        $this->sqlite->exec('CREATE INDEX idx_poule_judoka_poule ON poule_judoka(poule_id)');
        $this->sqlite->exec('CREATE INDEX idx_poule_judoka_judoka ON poule_judoka(judoka_id)');
        $this->sqlite->exec('CREATE INDEX idx_device_toegangen_toernooi ON device_toegangen(toernooi_id)');
    }

    private function exportToernooi(Toernooi $toernooi): void
    {
        $stmt = $this->sqlite->prepare('
            INSERT INTO toernooien (id, naam, slug, organisatie, datum, locatie, aantal_matten, aantal_blokken,
                wedstrijd_systeem, eliminatie_type, aantal_brons, gewicht_tolerantie, gebruik_gewichtsklassen,
                wedstrijd_schemas, gewichtsklassen, code_hoofdjury, code_weging, code_mat, code_spreker, code_dojo,
                wachtwoord_admin, wachtwoord_jury, wachtwoord_weging, wachtwoord_mat, wachtwoord_spreker,
                is_actief, thema_kleur, locale, spreker_notities, best_of_three_bij_2,
                eliminatie_gewichtsklassen, kruisfinales_aantal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $toernooi->id,
            $toernooi->naam,
            $toernooi->slug,
            $toernooi->organisator?->naam,
            $toernooi->datum?->toDateString(),
            $toernooi->locatie,
            $toernooi->aantal_matten,
            $toernooi->aantal_blokken,
            $this->jsonOrString($toernooi->wedstrijd_systeem),
            $toernooi->eliminatie_type,
            $toernooi->aantal_brons,
            $toernooi->gewicht_tolerantie,
            $toernooi->gebruik_gewichtsklassen ? 1 : 0,
            $this->jsonOrString($toernooi->wedstrijd_schemas),
            $this->jsonOrString($toernooi->gewichtsklassen),
            $toernooi->code_hoofdjury,
            $toernooi->code_weging,
            $toernooi->code_mat,
            $toernooi->code_spreker,
            $toernooi->code_dojo,
            $toernooi->wachtwoord_admin,
            $toernooi->wachtwoord_jury,
            $toernooi->wachtwoord_weging,
            $toernooi->wachtwoord_mat,
            $toernooi->wachtwoord_spreker,
            $toernooi->is_actief ? 1 : 0,
            $toernooi->thema_kleur,
            $toernooi->locale,
            $toernooi->spreker_notities,
            $toernooi->best_of_three_bij_2 ? 1 : 0,
            $this->jsonOrString($toernooi->eliminatie_gewichtsklassen),
            $toernooi->kruisfinales_aantal,
        ]);
    }

    private function exportClubs(Toernooi $toernooi): void
    {
        $clubs = $toernooi->clubs()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO clubs (id, naam, afkorting, plaats, contact_naam, contact_email, contact_telefoon)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($clubs as $club) {
            $stmt->execute([
                $club->id, $club->naam, $club->afkorting, $club->plaats,
                $club->contact_naam, $club->contact_email, $club->contact_telefoon,
            ]);
        }
    }

    private function exportJudokas(Toernooi $toernooi): void
    {
        $judokas = $toernooi->judokas()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO judokas (id, toernooi_id, club_id, naam, voornaam, achternaam, geboortejaar, geslacht,
                band, gewicht, leeftijdsklasse, gewichtsklasse, judoka_code, aanwezigheid, gewicht_gewogen,
                opmerking, qr_code, categorie_key, sort_categorie)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($judokas as $j) {
            $stmt->execute([
                $j->id, $j->toernooi_id, $j->club_id, $j->naam, $j->voornaam, $j->achternaam,
                $j->geboortejaar, $j->geslacht, $j->band, $j->gewicht, $j->leeftijdsklasse,
                $j->gewichtsklasse, $j->judoka_code, $j->aanwezigheid, $j->gewicht_gewogen,
                $j->opmerking, $j->qr_code, $j->categorie_key, $j->sort_categorie,
            ]);
        }
    }

    private function exportBlokken(Toernooi $toernooi): void
    {
        $blokken = $toernooi->blokken()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO blokken (id, toernooi_id, nummer, starttijd, eindtijd, weging_gesloten, blok_label,
                weging_start_tijd, weging_stop_tijd)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($blokken as $b) {
            $stmt->execute([
                $b->id, $b->toernooi_id, $b->nummer, $b->starttijd, $b->eindtijd,
                $b->weging_gesloten ? 1 : 0, $b->blok_label, $b->weging_start_tijd, $b->weging_stop_tijd,
            ]);
        }
    }

    private function exportMatten(Toernooi $toernooi): void
    {
        $matten = $toernooi->matten()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO matten (id, toernooi_id, nummer, naam, kleur)
            VALUES (?, ?, ?, ?, ?)
        ');

        foreach ($matten as $m) {
            $stmt->execute([$m->id, $m->toernooi_id, $m->nummer, $m->naam, $m->kleur]);
        }
    }

    private function exportPoules(Toernooi $toernooi): void
    {
        $poules = $toernooi->poules()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO poules (id, toernooi_id, blok_id, mat_id, nummer, titel, leeftijdsklasse, gewichtsklasse,
                type, aantal_judokas, aantal_wedstrijden, vast, categorie_key, spreker_klaar, doorgestuurd_op,
                afgeroepen_at, huidige_wedstrijd_id, actieve_wedstrijd_id, barrage_van_poule_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($poules as $p) {
            $stmt->execute([
                $p->id, $p->toernooi_id, $p->blok_id, $p->mat_id, $p->nummer, $p->titel,
                $p->leeftijdsklasse, $p->gewichtsklasse, $p->type, $p->aantal_judokas,
                $p->aantal_wedstrijden, $p->vast ? 1 : 0, $p->categorie_key, $p->spreker_klaar ? 1 : 0,
                $p->doorgestuurd_op?->toDateTimeString(), $p->afgeroepen_at?->toDateTimeString(),
                $p->huidige_wedstrijd_id, $p->actieve_wedstrijd_id, $p->barrage_van_poule_id,
            ]);
        }
    }

    private function exportPouleJudoka(Toernooi $toernooi): void
    {
        $pouleIds = $toernooi->poules()->pluck('id');
        $pivots = DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO poule_judoka (id, poule_id, judoka_id, positie, punten, gewonnen, verloren, gelijk, eindpositie)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($pivots as $pj) {
            $stmt->execute([
                $pj->id, $pj->poule_id, $pj->judoka_id, $pj->positie, $pj->punten,
                $pj->gewonnen, $pj->verloren, $pj->gelijk, $pj->eindpositie,
            ]);
        }
    }

    private function exportWedstrijden(Toernooi $toernooi): void
    {
        $pouleIds = $toernooi->poules()->pluck('id');
        $wedstrijden = Wedstrijd::whereIn('poule_id', $pouleIds)->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO wedstrijden (id, poule_id, judoka_wit_id, judoka_blauw_id, volgorde, winnaar_id,
                score_wit, score_blauw, uitslag_type, is_gespeeld, gespeeld_op, ronde, potje_nummer, poule_volgorde)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($wedstrijden as $w) {
            $stmt->execute([
                $w->id, $w->poule_id, $w->judoka_wit_id, $w->judoka_blauw_id, $w->volgorde,
                $w->winnaar_id, $w->score_wit, $w->score_blauw, $w->uitslag_type,
                $w->is_gespeeld ? 1 : 0, $w->gespeeld_op?->toDateTimeString(),
                $w->ronde, $w->potje_nummer, $w->poule_volgorde,
            ]);
        }
    }

    private function exportDeviceToegangen(Toernooi $toernooi): void
    {
        $devices = $toernooi->deviceToegangen()->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO device_toegangen (id, toernooi_id, rol, mat_nummer, code, pincode, device_token,
                device_info, vrijwilliger_naam, vrijwilliger_email)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($devices as $d) {
            $stmt->execute([
                $d->id, $d->toernooi_id, $d->rol, $d->mat_nummer, $d->code, $d->pincode,
                $d->device_token, $d->device_info, $d->vrijwilliger_naam, $d->vrijwilliger_email,
            ]);
        }
    }

    private function exportCoachKaarten(Toernooi $toernooi): void
    {
        $kaarten = \App\Models\CoachKaart::where('toernooi_id', $toernooi->id)->get();
        $stmt = $this->sqlite->prepare('
            INSERT INTO coach_kaarten (id, toernooi_id, club_id, naam, qr_code, is_gescand, pincode)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($kaarten as $k) {
            $stmt->execute([
                $k->id, $k->toernooi_id, $k->club_id, $k->naam, $k->qr_code,
                $k->is_gescand ? 1 : 0, $k->pincode,
            ]);
        }
    }

    private function createOfflineMeta(Toernooi $toernooi): void
    {
        $stmt = $this->sqlite->prepare('INSERT INTO offline_meta (key, value) VALUES (?, ?)');
        $stmt->execute(['toernooi_id', (string) $toernooi->id]);
        $stmt->execute(['toernooi_naam', $toernooi->naam]);
        $stmt->execute(['exported_at', now()->toIso8601String()]);
        $stmt->execute(['source_url', config('app.url')]);
        $stmt->execute(['app_version', config('app.version', '1.0.0')]);
    }

    /**
     * Convert arrays to JSON strings, pass strings through.
     */
    private function jsonOrString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Clean up temporary SQLite export files older than 1 hour.
     */
    public function cleanup(): int
    {
        $count = 0;
        $pattern = storage_path('app/offline_*.sqlite');

        foreach (glob($pattern) as $file) {
            if (filemtime($file) < time() - 3600) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}

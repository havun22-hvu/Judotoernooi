<?php

namespace App\Services;

use App\Mail\FactuurMail;
use App\Models\ToernooiBetaling;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class FactuurService
{
    /**
     * Generate invoice and send it to the organisator.
     */
    public function verstuurFactuur(ToernooiBetaling $betaling): void
    {
        try {
            $pdfPath = $this->genereerFactuur($betaling);

            $organisator = $betaling->organisator;
            $email = $organisator->factuur_email ?? $organisator->user?->email;

            if (! $email) {
                Log::warning("Factuur {$betaling->factuurnummer}: geen email adres gevonden voor organisator {$organisator->id}");
                return;
            }

            Mail::to($email)->send(new FactuurMail($betaling, $pdfPath));

            Log::info("Factuur {$betaling->factuurnummer} verstuurd naar {$email}");
        } catch (\Throwable $e) {
            Log::error("Factuur generatie/versturen mislukt voor betaling {$betaling->id}: {$e->getMessage()}");
        }
    }

    /**
     * Generate invoice number and PDF. Returns path to PDF file.
     */
    public function genereerFactuur(ToernooiBetaling $betaling): string
    {
        if (! $betaling->factuurnummer) {
            $betaling->update([
                'factuurnummer' => $this->volgendFactuurnummer($betaling->betaald_op ?? now()),
            ]);
        }

        $organisator = $betaling->organisator;
        $toernooi = $betaling->toernooi;

        $tierInfo = FreemiumService::STAFFELS[$betaling->tier] ?? null;
        $omschrijving = $tierInfo
            ? "JudoToernooi Upgrade - tot {$tierInfo['max']} judoka's"
            : "JudoToernooi Upgrade - {$betaling->tier}";

        $data = [
            'betaling' => $betaling,
            'organisator' => $organisator,
            'toernooi' => $toernooi,
            'factuurnummer' => $betaling->factuurnummer,
            'factuurdatum' => ($betaling->betaald_op ?? now())->format('d-m-Y'),
            'omschrijving' => $omschrijving,
            'bedrag' => $betaling->bedrag,
            'config' => config('factuur'),
        ];

        $pdf = Pdf::loadView('pdf.factuur', $data);

        $filename = "facturen/{$betaling->factuurnummer}.pdf";
        Storage::disk('local')->put($filename, $pdf->output());

        return Storage::disk('local')->path($filename);
    }

    /**
     * Generate next sequential invoice number for a given date.
     * Format: JT-YYYYMMDD-NNN
     */
    private function volgendFactuurnummer(Carbon $datum): string
    {
        $prefix = 'JT-' . $datum->format('Ymd') . '-';

        $laatsteNummer = ToernooiBetaling::where('factuurnummer', 'like', $prefix . '%')
            ->orderByDesc('factuurnummer')
            ->value('factuurnummer');

        if ($laatsteNummer) {
            $volgnummer = (int) substr($laatsteNummer, -3) + 1;
        } else {
            $volgnummer = 1;
        }

        return $prefix . str_pad($volgnummer, 3, '0', STR_PAD_LEFT);
    }
}

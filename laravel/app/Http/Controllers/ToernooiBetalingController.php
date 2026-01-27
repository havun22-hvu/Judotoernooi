<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\FreemiumService;
use App\Services\MollieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ToernooiBetalingController extends Controller
{
    public function __construct(
        private FreemiumService $freemiumService,
        private MollieService $mollieService
    ) {}

    /**
     * Show upgrade options page
     */
    public function showUpgrade(Organisator $organisator, Toernooi $toernooi): View|RedirectResponse
    {
        // Already paid - redirect to toernooi
        if ($toernooi->isPaidTier()) {
            return redirect()->route('toernooi.show', $toernooi->routeParams())
                ->with('info', 'Dit toernooi heeft al een betaald abonnement.');
        }

        $organisator = Auth::guard('organisator')->user();
        $upgradeOptions = $this->freemiumService->getUpgradeOptions($toernooi);
        $status = $this->freemiumService->getStatus($toernooi);

        // Check if KYC is complete
        $kycCompleet = $organisator->isKycCompleet();

        return view('pages.toernooi.upgrade', [
            'toernooi' => $toernooi,
            'upgradeOptions' => $upgradeOptions,
            'status' => $status,
            'organisator' => $organisator,
            'kycCompleet' => $kycCompleet,
        ]);
    }

    /**
     * Save KYC data
     */
    public function saveKyc(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'organisatie_naam' => 'required|string|max:255',
            'kvk_nummer' => 'nullable|string|max:20',
            'btw_nummer' => 'nullable|string|max:30',
            'straat' => 'required|string|max:255',
            'postcode' => 'required|string|max:10',
            'plaats' => 'required|string|max:100',
            'land' => 'required|string|max:100',
            'contactpersoon' => 'required|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'factuur_email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        $organisator = Auth::guard('organisator')->user();
        $organisator->update($validated);
        $organisator->markKycCompleet();

        return redirect()->route('toernooi.upgrade', $toernooi->routeParams())
            ->with('success', 'Facturatiegegevens opgeslagen. Je kunt nu een staffel kiezen.');
    }

    /**
     * Start the upgrade payment process
     */
    public function startPayment(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $organisator = Auth::guard('organisator')->user();

        // KYC must be complete before payment
        if (!$organisator->isKycCompleet()) {
            return redirect()->route('toernooi.upgrade', $toernooi->routeParams())
                ->with('error', 'Vul eerst je facturatiegegevens in voordat je kunt betalen.');
        }

        $validated = $request->validate([
            'tier' => 'required|string',
        ]);

        $tierInfo = $this->freemiumService->getTierInfo($validated['tier']);
        if (!$tierInfo) {
            return back()->with('error', 'Ongeldige staffel geselecteerd.');
        }

        $prijs = $tierInfo['prijs'];
        $maxJudokas = $tierInfo['max'];

        $organisator = Auth::guard('organisator')->user();

        // Test organisator: bypass payment, direct upgrade
        if ($organisator->isTest()) {
            $betaling = ToernooiBetaling::create([
                'toernooi_id' => $toernooi->id,
                'organisator_id' => $organisator->id,
                'mollie_payment_id' => 'test_' . uniqid(),
                'bedrag' => 0, // No fees for test organisators
                'tier' => $validated['tier'],
                'max_judokas' => $maxJudokas,
                'status' => ToernooiBetaling::STATUS_PAID,
                'betaald_op' => now(),
            ]);

            // Direct upgrade toernooi
            $toernooi->update([
                'plan_type' => $validated['tier'],
                'max_judokas' => $maxJudokas,
            ]);

            return redirect()->route('toernooi.upgrade.succes', $toernooi->routeParamsWith(['betaling' => $betaling]))
                ->with('success', '✓ Test upgrade succesvol - geen betaling nodig');
        }

        // Create betaling record
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'bedrag' => $prijs,
            'tier' => $validated['tier'],
            'max_judokas' => $maxJudokas,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ]);

        $description = "JudoToernooi Upgrade: {$toernooi->naam} - {$validated['tier']} judoka's";
        $redirectUrl = route('toernooi.upgrade.succes', ['toernooi' => $toernooi, 'betaling' => $betaling]);

        // Simulation mode for staging
        if ($this->mollieService->isSimulationMode()) {
            $payment = $this->mollieService->simulatePayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($prijs, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => route('mollie.webhook.toernooi'),
                'metadata' => ['toernooi_betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        }

        // Real Mollie payment (Platform mode - goes to JudoToernooi)
        try {
            $payment = $this->createPlatformPayment($toernooi, $betaling, $prijs, $description, $redirectUrl);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        } catch (\Exception $e) {
            \Log::error('Toernooi upgrade payment failed', ['error' => $e->getMessage()]);
            $betaling->update(['status' => ToernooiBetaling::STATUS_FAILED]);

            return back()->with('error', 'Fout bij aanmaken betaling: ' . $e->getMessage());
        }
    }

    /**
     * Create a platform payment (goes to JudoToernooi's Mollie account)
     */
    private function createPlatformPayment(Toernooi $toernooi, ToernooiBetaling $betaling, float $prijs, string $description, string $redirectUrl): object
    {
        $apiKey = $this->mollieService->getPlatformApiKey();

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post(config('services.mollie.api_url') . '/payments', [
            'amount' => ['currency' => 'EUR', 'value' => number_format($prijs, 2, '.', '')],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => route('mollie.webhook.toernooi'),
            'metadata' => [
                'toernooi_betaling_id' => $betaling->id,
                'toernooi_id' => $toernooi->id,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Mollie API error: ' . $response->body());
        }

        return $response->object();
    }

    /**
     * Payment success page
     */
    public function success(Organisator $organisator, Toernooi $toernooi, ToernooiBetaling $betaling): View|RedirectResponse
    {
        // Verify this betaling belongs to this toernooi
        if ($betaling->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        // Refresh toernooi to get updated plan_type
        $toernooi->refresh();

        return view('pages.toernooi.upgrade-succes', [
            'toernooi' => $toernooi,
            'betaling' => $betaling,
        ]);
    }

    /**
     * Payment cancelled
     */
    public function cancelled(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        return redirect()->route('toernooi.upgrade', $toernooi->routeParams())
            ->with('warning', 'Betaling geannuleerd. Je kunt het opnieuw proberen.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\FreemiumService;
use App\Services\MollieService;
use App\Services\PaymentProviderFactory;
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
        $organisator = Auth::guard('organisator')->user();
        $upgradeOptions = $this->freemiumService->getUpgradeOptions($toernooi);
        $status = $this->freemiumService->getStatus($toernooi);

        // For paid tier: filter to only show HIGHER tiers than current
        if ($toernooi->isPaidTier()) {
            $currentMax = $toernooi->paid_max_judokas ?? 0;
            $upgradeOptions = array_filter($upgradeOptions, fn($opt) => $opt['max'] > $currentMax);
            $upgradeOptions = array_values($upgradeOptions); // Re-index

            // If no higher tiers available, show message
            if (empty($upgradeOptions)) {
                return redirect()->route('toernooi.show', $toernooi->routeParams())
                    ->with('info', 'Je hebt al het hoogste abonnement (max ' . $currentMax . ' judoka\'s).');
            }
        }

        // Check if KYC is complete
        $kycCompleet = $organisator->isKycCompleet();

        return view('pages.toernooi.upgrade', [
            'toernooi' => $toernooi,
            'upgradeOptions' => $upgradeOptions,
            'status' => $status,
            'organisator' => $organisator,
            'kycCompleet' => $kycCompleet,
            'isReUpgrade' => $toernooi->isPaidTier(),
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
            'telefoon' => ['nullable', 'string', 'max:20', 'regex:/^(\+31|0)[1-9][\d\s\-]{7,12}$/'],
            'factuur_email' => 'required|email|max:255',
            'website' => 'nullable|string|max:255',
        ], [
            'telefoon.regex' => 'Voer een geldig Nederlands telefoonnummer in (bijv. 06-12345678)',
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
            'payment_provider' => 'nullable|string|in:mollie,stripe',
        ]);

        $tierInfo = $this->freemiumService->getTierInfo($validated['tier']);
        if (!$tierInfo) {
            return back()->with('error', 'Ongeldige staffel geselecteerd.');
        }

        $alBetaald = $this->freemiumService->getAlBetaaldePrijs($toernooi);
        $prijs = max(0, $tierInfo['prijs'] - $alBetaald);
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

            // Remove demo judokas before upgrade
            $this->freemiumService->removeDemoJudokas($toernooi);

            // Direct upgrade toernooi
            $toernooi->update([
                'plan_type' => 'paid',
                'paid_tier' => $validated['tier'],
                'paid_max_judokas' => $maxJudokas,
                'max_judokas' => $maxJudokas, // Also set the visible max_judokas field
                'paid_at' => now(),
                'toernooi_betaling_id' => $betaling->id,
            ]);

            return redirect()->route('toernooi.upgrade.succes', ['organisator' => $organisator, 'toernooi' => $toernooi, 'betaling' => $betaling])
                ->with('success', '✓ Test upgrade succesvol - geen betaling nodig');
        }

        // Upgrade payments: provider comes from the form (user clicks Mollie or Stripe button)
        $providerName = $validated['payment_provider'] ?? 'mollie';
        $provider = PaymentProviderFactory::make($providerName);

        // Create betaling record
        $betaling = ToernooiBetaling::create([
            'toernooi_id' => $toernooi->id,
            'organisator_id' => $organisator->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'payment_provider' => $providerName,
            'bedrag' => $prijs,
            'tier' => $validated['tier'],
            'max_judokas' => $maxJudokas,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ]);

        $description = sprintf('JT-%s-%s Upgrade %s', now()->format('Ymd'), $toernooi->slug, $validated['tier']);
        $redirectUrl = route('toernooi.upgrade.succes', ['organisator' => $organisator, 'toernooi' => $toernooi, 'betaling' => $betaling]);
        $cancelUrl = route('toernooi.upgrade.geannuleerd', $toernooi->routeParams());
        $webhookRoute = $providerName === 'stripe' ? route('stripe.webhook.toernooi') : route('mollie.webhook.toernooi');

        // Simulation mode for staging
        if ($provider->isSimulationMode()) {
            $result = $provider->simulatePayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($prijs, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => $webhookRoute,
                'metadata' => ['toernooi_betaling_id' => $betaling->id],
            ]);

            $paymentIdField = $providerName === 'stripe' ? 'stripe_payment_id' : 'mollie_payment_id';
            $betaling->update([$paymentIdField => $result->id]);

            return redirect($result->checkoutUrl);
        }

        // Real payment (Platform mode - goes to JudoToernooi)
        try {
            if (!$provider->isAvailable()) {
                throw new \RuntimeException('Betaaldienst tijdelijk niet beschikbaar');
            }

            $result = $provider->createPlatformPayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($prijs, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'cancelUrl' => $cancelUrl,
                'webhookUrl' => $webhookRoute,
                'metadata' => [
                    'toernooi_betaling_id' => $betaling->id,
                    'toernooi_id' => $toernooi->id,
                ],
            ]);

            $paymentIdField = $providerName === 'stripe' ? 'stripe_payment_id' : 'mollie_payment_id';
            $betaling->update([$paymentIdField => $result->id]);

            return redirect($result->checkoutUrl);
        } catch (\Exception $e) {
            \Log::error('Toernooi upgrade payment failed', ['provider' => $providerName, 'error' => $e->getMessage()]);
            $betaling->update(['status' => ToernooiBetaling::STATUS_FAILED]);

            return back()->with('error', 'Fout bij aanmaken betaling: ' . $e->getMessage());
        }
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

<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\MollieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MollieController extends Controller
{
    public function __construct(
        private MollieService $mollieService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | OAuth Flow (Mollie Connect)
    |--------------------------------------------------------------------------
    */

    /**
     * Redirect to Mollie OAuth authorization
     */
    public function authorize(Toernooi $toernooi): RedirectResponse
    {
        $url = $this->mollieService->getOAuthAuthorizeUrl($toernooi);
        return redirect($url);
    }

    /**
     * Handle OAuth callback from Mollie
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->get('state');
        $code = $request->get('code');
        $error = $request->get('error');

        // Validate state
        $toernooiId = $this->mollieService->validateOAuthState($state);
        if (!$toernooiId) {
            return redirect()->route('organisator.dashboard')
                ->with('error', 'Ongeldige OAuth state');
        }

        $toernooi = Toernooi::findOrFail($toernooiId);

        // Handle error from Mollie
        if ($error) {
            return redirect()->route('toernooi.edit', $toernooi)
                ->with('error', 'Mollie koppeling geannuleerd: ' . $request->get('error_description', $error));
        }

        try {
            // Exchange code for tokens
            $tokens = $this->mollieService->exchangeCodeForTokens($code);
            $this->mollieService->saveTokensToToernooi($toernooi, $tokens);

            return redirect()->route('toernooi.edit', $toernooi)
                ->with('success', 'Mollie account succesvol gekoppeld!');
        } catch (\Exception $e) {
            return redirect()->route('toernooi.edit', $toernooi)
                ->with('error', 'Fout bij koppelen: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Mollie account
     */
    public function disconnect(Toernooi $toernooi): RedirectResponse
    {
        $this->mollieService->disconnectFromToernooi($toernooi);

        return redirect()->route('toernooi.edit', $toernooi)
            ->with('success', 'Mollie account ontkoppeld');
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Webhook
    |--------------------------------------------------------------------------
    */

    /**
     * Handle Mollie webhook for payment status updates
     */
    public function webhook(Request $request): \Illuminate\Http\Response
    {
        $paymentId = $request->input('id');

        if (!$paymentId) {
            return response('Missing payment ID', 400);
        }

        // Find betaling by mollie_payment_id
        $betaling = Betaling::where('mollie_payment_id', $paymentId)->first();

        if (!$betaling) {
            // Could be a simulated payment or unknown
            return response('OK', 200);
        }

        try {
            $toernooi = $betaling->toernooi;
            $this->mollieService->ensureValidToken($toernooi);

            $payment = $this->mollieService->getPayment($toernooi, $paymentId);

            $this->updateBetalingStatus($betaling, $payment->status);

            return response('OK', 200);
        } catch (\Exception $e) {
            \Log::error('Mollie webhook error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Update betaling status based on Mollie status
     */
    private function updateBetalingStatus(Betaling $betaling, string $status): void
    {
        $statusMapping = [
            'paid' => Betaling::STATUS_PAID,
            'failed' => Betaling::STATUS_FAILED,
            'expired' => Betaling::STATUS_EXPIRED,
            'canceled' => Betaling::STATUS_CANCELED,
            'pending' => Betaling::STATUS_PENDING,
            'open' => Betaling::STATUS_OPEN,
        ];

        $newStatus = $statusMapping[$status] ?? $betaling->status;

        $betaling->update(['status' => $newStatus]);

        // If paid, mark judokas as paid
        if ($newStatus === Betaling::STATUS_PAID && !$betaling->betaald_op) {
            $betaling->markeerAlsBetaald();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Simulation (Staging)
    |--------------------------------------------------------------------------
    */

    /**
     * Simulate payment page (for staging without real Mollie)
     */
    public function simulate(Request $request): View
    {
        $paymentId = $request->get('payment_id');
        $betaling = Betaling::where('mollie_payment_id', $paymentId)->first();

        return view('pages.betaling.simulate', [
            'paymentId' => $paymentId,
            'betaling' => $betaling,
        ]);
    }

    /**
     * Complete simulated payment
     */
    public function simulateComplete(Request $request): RedirectResponse
    {
        $paymentId = $request->input('payment_id');
        $status = $request->input('status', 'paid');

        $betaling = Betaling::where('mollie_payment_id', $paymentId)->first();

        if ($betaling) {
            $this->updateBetalingStatus($betaling, $status);
        }

        // Get redirect URL from betaling metadata or fallback
        $redirectUrl = $betaling?->metadata['redirect_url'] ?? route('organisator.dashboard');

        return redirect($redirectUrl);
    }
}

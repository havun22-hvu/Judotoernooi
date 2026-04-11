<?php

namespace App\Http\Controllers;

use App\Exceptions\MollieException;
use App\Models\Organisator;
use App\Models\Betaling;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\MollieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function authorize(Organisator $organisator, Toernooi $toernooi): RedirectResponse
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
            // Redirect to login if state is invalid (session likely expired)
            return redirect()->route('organisator.login')
                ->with('error', 'Ongeldige OAuth state - sessie mogelijk verlopen');
        }

        $toernooi = Toernooi::findOrFail($toernooiId);

        // Handle error from Mollie
        if ($error) {
            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Mollie koppeling geannuleerd: ' . $request->get('error_description', $error));
        }

        try {
            // Exchange code for tokens
            $tokens = $this->mollieService->exchangeCodeForTokens($code);
            $this->mollieService->saveTokensToToernooi($toernooi, $tokens);

            Log::info('Mollie account connected', [
                'toernooi_id' => $toernooi->id,
                'organization' => $toernooi->mollie_organization_name,
            ]);

            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('success', 'Mollie account succesvol gekoppeld!');
        } catch (MollieException $e) {
            $e->log();
            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', $e->getUserMessage());
        } catch (\Exception $e) {
            Log::error('Unexpected error during Mollie OAuth', [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Er ging iets mis bij het koppelen. Probeer het opnieuw.');
        }
    }

    /**
     * Disconnect Mollie account
     */
    public function disconnect(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $this->mollieService->disconnectFromToernooi($toernooi);

        return redirect()->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', 'Mollie account ontkoppeld');
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Webhook
    |--------------------------------------------------------------------------
    */

    /**
     * Handle Mollie webhook for payment status updates.
     *
     * Security:
     * - Mollie does not sign webhooks. We verify authenticity by re-fetching
     *   the payment directly from the Mollie API — the webhook body itself
     *   (besides the payment ID) is never trusted.
     * - Idempotency is enforced via payment_processed_at: once a betaling
     *   has been finalized as "paid", subsequent webhook calls are ignored
     *   to prevent double-processing (double invoice, double mark paid).
     * - Payment finalization runs inside a DB transaction so partial state
     *   is impossible on failure.
     * - On unexpected errors we return 500 so Mollie retries later.
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

        // Idempotency check — already finalized, do not re-process.
        if ($betaling->payment_processed_at !== null) {
            Log::info('Mollie webhook ignored (already processed)', [
                'payment_id' => $paymentId,
                'betaling_id' => $betaling->id,
            ]);
            return response('Already processed', 200);
        }

        try {
            $toernooi = $betaling->toernooi;
            $this->mollieService->ensureValidToken($toernooi);

            // Never trust webhook payload — re-fetch directly from Mollie.
            $payment = $this->mollieService->getPayment($toernooi, $paymentId);

            DB::transaction(function () use ($betaling, $payment) {
                $this->updateBetalingStatus($betaling, $payment->status);
            });

            Log::info('Mollie webhook processed', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
            ]);

            return response('OK', 200);
        } catch (MollieException $e) {
            $e->log();
            // Return 500 so Mollie retries — transient API/auth issues
            // should not silently swallow the webhook.
            return response('Error', 500);
        } catch (\Exception $e) {
            Log::error('Unexpected Mollie webhook error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Handle Mollie webhook for toernooi upgrade payments.
     *
     * Same security model as webhook(): re-fetch from Mollie API, enforce
     * idempotency, wrap in a transaction, and return 500 so Mollie retries
     * on transient errors.
     */
    public function webhookToernooi(Request $request): \Illuminate\Http\Response
    {
        $paymentId = $request->input('id');

        if (!$paymentId) {
            return response('Missing payment ID', 400);
        }

        // Find toernooi betaling by mollie_payment_id
        $betaling = ToernooiBetaling::where('mollie_payment_id', $paymentId)->first();

        if (!$betaling) {
            // Could be a simulated payment or unknown
            return response('OK', 200);
        }

        // Idempotency check — already finalized, do not re-process.
        if ($betaling->payment_processed_at !== null) {
            Log::info('Mollie toernooi webhook ignored (already processed)', [
                'payment_id' => $paymentId,
                'toernooi_betaling_id' => $betaling->id,
            ]);
            return response('Already processed', 200);
        }

        try {
            $apiKey = $this->mollieService->getPlatformApiKey();

            // Never trust webhook payload — re-fetch directly from Mollie.
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->get(config('services.mollie.api_url') . '/payments/' . $paymentId);

            if (!$response->successful()) {
                throw MollieException::apiError('/payments/' . $paymentId, $response->body(), $response->status());
            }

            $payment = $response->object();

            DB::transaction(function () use ($betaling, $payment) {
                $this->updateToernooiBetalingStatus($betaling, $payment->status);
            });

            Log::info('Mollie toernooi webhook processed', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
            ]);

            return response('OK', 200);
        } catch (MollieException $e) {
            $e->log();
            // Return 500 so Mollie retries on transient API errors.
            return response('Error', 500);
        } catch (\Exception $e) {
            Log::error('Unexpected Mollie toernooi webhook error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Final statuses that should flip the idempotency marker so the same
     * webhook cannot be re-processed (and cause duplicate side-effects).
     */
    private const FINAL_STATUSES = ['paid', 'failed', 'expired', 'canceled'];

    /**
     * Update toernooi betaling status based on Mollie status.
     *
     * Sets payment_processed_at on final statuses so repeated webhook
     * deliveries become no-ops (see idempotency guard in webhook()).
     */
    private function updateToernooiBetalingStatus(ToernooiBetaling $betaling, string $status): void
    {
        $statusMapping = [
            'paid' => ToernooiBetaling::STATUS_PAID,
            'failed' => ToernooiBetaling::STATUS_FAILED,
            'expired' => ToernooiBetaling::STATUS_EXPIRED,
            'canceled' => ToernooiBetaling::STATUS_CANCELED,
            'open' => ToernooiBetaling::STATUS_OPEN,
        ];

        $newStatus = $statusMapping[$status] ?? $betaling->status;

        $betaling->update(['status' => $newStatus]);

        // If paid, activate the paid plan (invoices + updates plan)
        if ($newStatus === ToernooiBetaling::STATUS_PAID && !$betaling->betaald_op) {
            $betaling->markeerAlsBetaald();
        }

        // Mark as processed on final statuses to enforce webhook idempotency.
        if (in_array($status, self::FINAL_STATUSES, true)) {
            $betaling->forceFill(['payment_processed_at' => now()])->save();
        }
    }

    /**
     * Update betaling status based on Mollie status.
     *
     * Sets payment_processed_at on final statuses so repeated webhook
     * deliveries become no-ops (see idempotency guard in webhook()).
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

        // Mark as processed on final statuses to enforce webhook idempotency.
        if (in_array($status, self::FINAL_STATUSES, true)) {
            $betaling->forceFill(['payment_processed_at' => now()])->save();
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
        $betaling = Betaling::where('mollie_payment_id', $paymentId)->orWhere('stripe_payment_id', $paymentId)->first()
            ?? ToernooiBetaling::where('mollie_payment_id', $paymentId)->orWhere('stripe_payment_id', $paymentId)->first();

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

        $betaling = Betaling::where('mollie_payment_id', $paymentId)->orWhere('stripe_payment_id', $paymentId)->first();
        $toernooiBetaling = ToernooiBetaling::where('mollie_payment_id', $paymentId)->orWhere('stripe_payment_id', $paymentId)->first();

        if ($betaling) {
            $this->updateBetalingStatus($betaling, $status);
        }

        if ($toernooiBetaling) {
            $this->updateToernooiBetalingStatus($toernooiBetaling, $status);
        }

        // Get redirect URL from betaling metadata or fallback
        $fallbackUrl = auth('organisator')->check()
            ? route('organisator.dashboard', ['organisator' => auth('organisator')->user()->slug])
            : route('organisator.login');
        $redirectUrl = $betaling?->metadata['redirect_url'] ?? $fallbackUrl;

        return redirect($redirectUrl);
    }
}

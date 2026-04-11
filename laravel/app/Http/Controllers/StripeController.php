<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct(
        private StripePaymentProvider $stripeProvider
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Stripe Connect Onboarding (Account Links)
    |--------------------------------------------------------------------------
    */

    /**
     * Create connected account and redirect to Stripe-hosted onboarding.
     */
    public function authorize(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        try {
            $url = $this->stripeProvider->getOAuthAuthorizeUrl($toernooi);

            return redirect($url);
        } catch (\Exception $e) {
            Log::error('Stripe Connect authorize failed', [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Er ging iets mis bij het starten van Stripe onboarding. Probeer het opnieuw.');
        }
    }

    /**
     * Handle return from Stripe Account Link onboarding.
     * User is redirected here after completing (or abandoning) onboarding.
     */
    public function callback(Request $request): RedirectResponse
    {
        $toernooiId = (int) $request->get('toernooi_id');
        $hash = $request->get('hash');

        if (!$this->stripeProvider->validateCallbackHash($toernooiId, $hash)) {
            return redirect()->route('organisator.login')
                ->with('error', 'Ongeldige callback - sessie mogelijk verlopen');
        }

        $toernooi = Toernooi::findOrFail($toernooiId);

        try {
            $account = $this->stripeProvider->getAccount($toernooi->stripe_account_id);

            if ($account->charges_enabled && $account->payouts_enabled) {
                // Fully onboarded — activate Connect mode
                $toernooi->update(['mollie_mode' => 'connect']);

                Log::info('Stripe account fully onboarded', [
                    'toernooi_id' => $toernooi->id,
                    'stripe_account_id' => $account->id,
                ]);

                return redirect()->route('toernooi.edit', $toernooi->routeParams())
                    ->with('success', 'Stripe account succesvol gekoppeld! Betalingen gaan nu direct naar jouw rekening.');
            }

            // Not fully onboarded yet — user abandoned or Stripe needs more info
            Log::warning('Stripe account not fully onboarded', [
                'toernooi_id' => $toernooi->id,
                'stripe_account_id' => $account->id,
                'charges_enabled' => $account->charges_enabled,
            ]);

            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('warning', 'Stripe onboarding is nog niet afgerond. Klik opnieuw op "Koppel Stripe" om verder te gaan.');
        } catch (\Exception $e) {
            Log::error('Stripe onboarding callback error', [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Er ging iets mis bij het koppelen van Stripe. Probeer het opnieuw.');
        }
    }

    /**
     * Disconnect Stripe account from tournament.
     */
    public function disconnect(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $this->stripeProvider->disconnect($toernooi);

        return redirect()->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', 'Stripe account ontkoppeld');
    }

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */

    /**
     * Handle Stripe webhook for coach payment status updates.
     *
     * Security:
     * - Stripe signs every webhook. We verify the Stripe-Signature header
     *   via the SDK before trusting any event payload. Invalid signature
     *   returns 400 and the event is dropped.
     * - Idempotency is enforced via payment_processed_at on Betaling so
     *   the same event (retried by Stripe) never produces duplicate
     *   side-effects.
     * - All status mutations run inside a DB transaction.
     * - Unexpected exceptions bubble as 500 so Stripe retries with backoff.
     */
    public function webhook(Request $request): \Illuminate\Http\Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            return response('Missing signature', 400);
        }

        try {
            $event = $this->stripeProvider->verifyWebhookSignature($payload, $signature);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        try {
            return $this->handleStripeEvent($event);
        } catch (\Exception $e) {
            Log::error('Unexpected Stripe webhook error', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return 500 so Stripe retries with exponential backoff.
            return response('Error', 500);
        }
    }

    /**
     * Stripe event types we act on, mapped to internal status strings.
     * Unlisted event types are acknowledged (200) without side-effects.
     */
    private const STRIPE_EVENT_STATUS_MAP = [
        'checkout.session.completed' => 'paid',
        'checkout.session.expired' => 'expired',
    ];

    /**
     * Dispatch a verified Stripe coach-payment event.
     *
     * Kept separate so the exception handler wraps everything cleanly.
     */
    private function handleStripeEvent(\Stripe\Event $event): \Illuminate\Http\Response
    {
        if (!isset(self::STRIPE_EVENT_STATUS_MAP[$event->type])) {
            return response('OK', 200);
        }

        $session = $event->data->object;
        $betaling = Betaling::where('stripe_payment_id', $session->id)->first();

        if (!$betaling) {
            return response('OK', 200);
        }

        if ($betaling->payment_processed_at !== null) {
            Log::info('Stripe webhook ignored (already processed)', [
                'session_id' => $session->id,
                'betaling_id' => $betaling->id,
            ]);
            return response('Already processed', 200);
        }

        $status = self::STRIPE_EVENT_STATUS_MAP[$event->type];

        DB::transaction(function () use ($betaling, $status) {
            $this->updateBetalingStatus($betaling, $status);
        });

        Log::info('Stripe webhook processed (coach payment)', [
            'session_id' => $session->id,
            'status' => $status,
        ]);

        return response('OK', 200);
    }

    /**
     * Handle Stripe webhook for toernooi upgrade payments.
     *
     * Same security model as webhook(): verified signature, idempotency
     * via payment_processed_at, DB transaction, and 500 on unexpected
     * errors so Stripe retries.
     */
    public function webhookToernooi(Request $request): \Illuminate\Http\Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            return response('Missing signature', 400);
        }

        try {
            $event = $this->stripeProvider->verifyWebhookSignature($payload, $signature);
        } catch (\Exception $e) {
            Log::warning('Stripe toernooi webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        try {
            return $this->handleStripeToernooiEvent($event);
        } catch (\Exception $e) {
            Log::error('Unexpected Stripe toernooi webhook error', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error' => $e->getMessage(),
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Dispatch a verified Stripe toernooi-upgrade event.
     */
    private function handleStripeToernooiEvent(\Stripe\Event $event): \Illuminate\Http\Response
    {
        if (!isset(self::STRIPE_EVENT_STATUS_MAP[$event->type])) {
            return response('OK', 200);
        }

        $session = $event->data->object;
        $betaling = ToernooiBetaling::where('stripe_payment_id', $session->id)->first();

        if (!$betaling) {
            return response('OK', 200);
        }

        if ($betaling->payment_processed_at !== null) {
            Log::info('Stripe toernooi webhook ignored (already processed)', [
                'session_id' => $session->id,
                'toernooi_betaling_id' => $betaling->id,
            ]);
            return response('Already processed', 200);
        }

        $status = self::STRIPE_EVENT_STATUS_MAP[$event->type];

        DB::transaction(function () use ($betaling, $status) {
            $this->updateToernooiBetalingStatus($betaling, $status);
        });

        Log::info('Stripe webhook processed (toernooi upgrade)', [
            'session_id' => $session->id,
            'status' => $status,
        ]);

        return response('OK', 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Update Helpers
    |--------------------------------------------------------------------------
    */

    private function updateBetalingStatus(Betaling $betaling, string $status): void
    {
        $statusMapping = [
            'paid' => Betaling::STATUS_PAID,
            'failed' => Betaling::STATUS_FAILED,
            'expired' => Betaling::STATUS_EXPIRED,
            'canceled' => Betaling::STATUS_CANCELED,
            'open' => Betaling::STATUS_OPEN,
        ];

        $newStatus = $statusMapping[$status] ?? $betaling->status;
        $attributes = ['status' => $newStatus];

        if (Betaling::isFinalStatus($newStatus)) {
            $attributes['payment_processed_at'] = now();
        }

        $betaling->update($attributes);

        if ($newStatus === Betaling::STATUS_PAID && !$betaling->betaald_op) {
            $betaling->markeerAlsBetaald();
        }
    }

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
        $attributes = ['status' => $newStatus];

        if (ToernooiBetaling::isFinalStatus($newStatus)) {
            $attributes['payment_processed_at'] = now();
        }

        $betaling->update($attributes);

        if ($newStatus === ToernooiBetaling::STATUS_PAID && !$betaling->betaald_op) {
            $betaling->markeerAlsBetaald();
        }
    }
}

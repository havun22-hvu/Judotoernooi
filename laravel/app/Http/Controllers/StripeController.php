<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct(
        private StripePaymentProvider $stripeProvider
    ) {}

    /*
    |--------------------------------------------------------------------------
    | OAuth Flow (Stripe Connect)
    |--------------------------------------------------------------------------
    */

    /**
     * Create connected account and redirect to Stripe onboarding.
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
            $this->stripeProvider->handleOAuthCallback($toernooi, '');

            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('success', 'Stripe account succesvol gekoppeld!');
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

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $betaling = Betaling::where('stripe_payment_id', $session->id)->first();

            if ($betaling) {
                $this->updateBetalingStatus($betaling, 'paid');

                Log::info('Stripe webhook processed (coach payment)', [
                    'session_id' => $session->id,
                    'status' => 'paid',
                ]);
            }
        } elseif ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;
            $betaling = Betaling::where('stripe_payment_id', $session->id)->first();

            if ($betaling) {
                $this->updateBetalingStatus($betaling, 'expired');
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle Stripe webhook for toernooi upgrade payments.
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

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $betaling = ToernooiBetaling::where('stripe_payment_id', $session->id)->first();

            if ($betaling) {
                $this->updateToernooiBetalingStatus($betaling, 'paid');

                Log::info('Stripe webhook processed (toernooi upgrade)', [
                    'session_id' => $session->id,
                    'status' => 'paid',
                ]);
            }
        } elseif ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;
            $betaling = ToernooiBetaling::where('stripe_payment_id', $session->id)->first();

            if ($betaling) {
                $this->updateToernooiBetalingStatus($betaling, 'expired');
            }
        }

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
        $betaling->update(['status' => $newStatus]);

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
        $betaling->update(['status' => $newStatus]);

        if ($newStatus === ToernooiBetaling::STATUS_PAID && !$betaling->betaald_op) {
            $betaling->markeerAlsBetaald();
        }
    }
}

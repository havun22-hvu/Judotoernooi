<?php

namespace Tests\Feature;

use App\DTOs\PaymentResult;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Tests for StripePaymentProvider methods that use the Stripe SDK.
 * Uses mock StripeClient services to avoid real API calls.
 */
class StripeProviderCoverageTest extends TestCase
{
    use RefreshDatabase;

    private StripePaymentProvider $provider;
    private $mockCheckoutSessions;
    private $mockAccounts;
    private $mockAccountLinks;

    protected function setUp(): void
    {
        parent::setUp();
        config(['observability.enabled' => false]);
        config(['services.stripe.secret' => 'sk_test_fake_key_for_testing']);
        config(['services.stripe.webhook_secret' => 'whsec_test_fake']);

        // Create mock service objects
        $this->mockCheckoutSessions = Mockery::mock();
        $this->mockAccounts = Mockery::mock();
        $this->mockAccountLinks = Mockery::mock();

        // Create a mock StripeClient — mock getService which __get delegates to
        $mockClient = Mockery::mock(StripeClient::class)->makePartial();
        $mockClient->shouldReceive('getService')->with('checkout')->andReturn(
            (object) ['sessions' => $this->mockCheckoutSessions]
        );
        $mockClient->shouldReceive('getService')->with('accounts')->andReturn($this->mockAccounts);
        $mockClient->shouldReceive('getService')->with('accountLinks')->andReturn($this->mockAccountLinks);

        $this->provider = new StripePaymentProvider();

        // Inject mock via reflection
        $reflection = new \ReflectionClass($this->provider);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($this->provider, $mockClient);
    }

    // --- createPayment ---

    public function test_create_payment_with_connected_account(): void
    {
        $toernooi = $this->createToernooi(['stripe_account_id' => 'acct_test123', 'payment_provider' => 'stripe']);

        $this->mockCheckoutSessions->shouldReceive('create')
            ->once()
            ->withArgs(function ($params) {
                return isset($params['payment_intent_data']['transfer_data']['destination'])
                    && $params['payment_intent_data']['transfer_data']['destination'] === 'acct_test123';
            })
            ->andReturn($this->makeSession('cs_test_123', 'open'));

        $result = $this->provider->createPayment($toernooi, [
            'amount' => ['value' => '25.00', 'currency' => 'EUR'],
            'description' => 'Inschrijving test',
            'redirectUrl' => 'https://example.com/success',
            'metadata' => ['judoka_id' => 42],
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('cs_test_123', $result->id);
    }

    public function test_create_payment_without_connected_account(): void
    {
        $toernooi = $this->createToernooi(['stripe_account_id' => null, 'payment_provider' => 'stripe']);

        $this->mockCheckoutSessions->shouldReceive('create')
            ->once()
            ->withArgs(fn ($params) => !isset($params['payment_intent_data']))
            ->andReturn($this->makeSession('cs_test_456', 'open'));

        $result = $this->provider->createPayment($toernooi, [
            'amount' => ['value' => '15.00', 'currency' => 'EUR'],
            'redirectUrl' => 'https://example.com/success',
        ]);

        $this->assertEquals('cs_test_456', $result->id);
    }

    // --- createPlatformPayment ---

    public function test_create_platform_payment(): void
    {
        $this->mockCheckoutSessions->shouldReceive('create')
            ->once()
            ->andReturn($this->makeSession('cs_platform_789', 'open'));

        $result = $this->provider->createPlatformPayment([
            'amount' => ['value' => '49.99', 'currency' => 'EUR'],
            'description' => 'JudoToernooi Upgrade',
            'redirectUrl' => 'https://example.com/upgrade/success',
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('cs_platform_789', $result->id);
    }

    // --- getPayment / getPlatformPayment ---

    public function test_get_payment(): void
    {
        $toernooi = $this->createToernooi();

        $this->mockCheckoutSessions->shouldReceive('retrieve')
            ->with('cs_retrieve_1')
            ->andReturn($this->makeSession('cs_retrieve_1', 'complete'));

        $result = $this->provider->getPayment($toernooi, 'cs_retrieve_1');

        $this->assertEquals('cs_retrieve_1', $result->id);
        $this->assertEquals('paid', $result->status);
    }

    public function test_get_platform_payment(): void
    {
        $this->mockCheckoutSessions->shouldReceive('retrieve')
            ->with('cs_retrieve_2')
            ->andReturn($this->makeSession('cs_retrieve_2', 'expired'));

        $result = $this->provider->getPlatformPayment('cs_retrieve_2');

        $this->assertEquals('expired', $result->status);
    }

    // --- handleOAuthCallback ---

    public function test_handle_oauth_callback_fully_onboarded(): void
    {
        $toernooi = $this->createToernooi(['stripe_account_id' => 'acct_onboarded']);

        $this->mockAccounts->shouldReceive('retrieve')
            ->with('acct_onboarded')
            ->once()
            ->andReturn($this->makeAccount('acct_onboarded', true, true));
        // Mockery verifies the `once()` expectation at teardown — no extra assert needed.

        $this->provider->handleOAuthCallback($toernooi, 'dummy_code');
    }

    public function test_handle_oauth_callback_not_fully_onboarded(): void
    {
        $toernooi = $this->createToernooi(['stripe_account_id' => 'acct_partial']);

        $this->mockAccounts->shouldReceive('retrieve')
            ->with('acct_partial')
            ->once()
            ->andReturn($this->makeAccount('acct_partial', false, false));

        $this->provider->handleOAuthCallback($toernooi, 'dummy_code');
    }

    // --- getAccount ---

    public function test_get_account(): void
    {
        $this->mockAccounts->shouldReceive('retrieve')
            ->with('acct_test_get')
            ->andReturn($this->makeAccount('acct_test_get', true, true));

        $account = $this->provider->getAccount('acct_test_get');

        $this->assertEquals('acct_test_get', $account->id);
    }

    // --- Helpers ---

    private function createToernooi(array $overrides = []): Toernooi
    {
        $organisator = Organisator::factory()->create();

        return Toernooi::factory()->create(array_merge([
            'organisator_id' => $organisator->id,
            'payment_provider' => 'stripe',
        ], $overrides));
    }

    private function makeSession(string $id, string $status): \Stripe\Checkout\Session
    {
        return \Stripe\Checkout\Session::constructFrom([
            'id' => $id,
            'object' => 'checkout.session',
            'status' => $status,
            'url' => "https://checkout.stripe.com/pay/{$id}",
            'amount_total' => 2500,
            'currency' => 'eur',
            'metadata' => [],
        ]);
    }

    private function makeAccount(string $id, bool $chargesEnabled, bool $payoutsEnabled): \Stripe\Account
    {
        return \Stripe\Account::constructFrom([
            'id' => $id,
            'object' => 'account',
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
        ]);
    }
}

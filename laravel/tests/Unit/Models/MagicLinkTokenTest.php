<?php

namespace Tests\Unit\Models;

use App\Models\MagicLinkToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagicLinkTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_creates_token_with_15_minute_expiry(): void
    {
        $before = now();
        $token = MagicLinkToken::generate('user@example.com');

        $this->assertSame('user@example.com', $token->email);
        $this->assertSame('register', $token->type);
        $this->assertSame(64, strlen($token->token));
        $this->assertNull($token->used_at);
        $this->assertGreaterThanOrEqual(14, $before->diffInMinutes($token->expires_at));
        $this->assertLessThanOrEqual(16, $before->diffInMinutes($token->expires_at));
    }

    #[Test]
    public function generate_normalises_email_to_lowercase_and_trims(): void
    {
        $token = MagicLinkToken::generate('  USER@Example.COM  ');

        $this->assertSame('user@example.com', $token->email);
    }

    #[Test]
    public function generate_stores_metadata_array(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'password_reset', ['ip' => '1.2.3.4']);

        $this->assertSame(['ip' => '1.2.3.4'], $token->fresh()->metadata);
    }

    #[Test]
    public function generate_deletes_old_unused_tokens_for_same_email_and_type(): void
    {
        $old = MagicLinkToken::generate('a@b.nl', 'register');
        $new = MagicLinkToken::generate('a@b.nl', 'register');

        $this->assertNull(MagicLinkToken::find($old->id));
        $this->assertNotNull(MagicLinkToken::find($new->id));
    }

    #[Test]
    public function generate_does_not_delete_tokens_of_different_type(): void
    {
        $login = MagicLinkToken::generate('a@b.nl', 'password_reset');
        MagicLinkToken::generate('a@b.nl', 'register');

        $this->assertNotNull(MagicLinkToken::find($login->id),
            'Login-token mag niet sneuvelen wanneer register-token wordt aangevraagd.');
    }

    #[Test]
    public function generate_does_not_delete_used_tokens(): void
    {
        $used = MagicLinkToken::generate('a@b.nl', 'register');
        $used->markUsed();

        MagicLinkToken::generate('a@b.nl', 'register');

        $this->assertNotNull(MagicLinkToken::find($used->id),
            'Gebruikte tokens blijven bestaan voor audit trail.');
    }

    #[Test]
    public function find_valid_returns_token_when_unused_and_unexpired(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');

        $found = MagicLinkToken::findValid($token->token, 'register');

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
    }

    #[Test]
    public function find_valid_returns_null_for_wrong_type(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');

        $this->assertNull(MagicLinkToken::findValid($token->token, 'password_reset'));
    }

    #[Test]
    public function find_valid_returns_null_for_used_token(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');
        $token->markUsed();

        $this->assertNull(MagicLinkToken::findValid($token->token, 'register'));
    }

    #[Test]
    public function find_valid_returns_null_for_expired_token(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');
        $token->update(['expires_at' => now()->subMinute()]);

        $this->assertNull(MagicLinkToken::findValid($token->token, 'register'));
    }

    #[Test]
    public function mark_used_sets_used_at_timestamp(): void
    {
        $token = MagicLinkToken::generate('a@b.nl');

        $this->assertFalse($token->isUsed());
        $token->markUsed();

        $this->assertTrue($token->fresh()->isUsed());
        $this->assertNotNull($token->fresh()->used_at);
    }

    #[Test]
    public function is_expired_reflects_expires_at(): void
    {
        $valid = MagicLinkToken::generate('a@b.nl');
        $this->assertFalse($valid->isExpired());

        $valid->update(['expires_at' => now()->subSecond()]);
        $this->assertTrue($valid->fresh()->isExpired());
    }
}

<?php

namespace Tests\Unit\Mail;

use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagicLinkMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function envelope_subject_for_register_type(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');

        $mail = new MagicLinkMail($token);

        $this->assertStringContainsString('registratie', $mail->envelope()->subject);
    }

    #[Test]
    public function envelope_subject_for_password_reset_type(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'password_reset');

        $mail = new MagicLinkMail($token);

        $this->assertStringContainsString('Wachtwoord', $mail->envelope()->subject);
    }

    #[Test]
    public function content_uses_register_route_for_register_token(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');

        $content = (new MagicLinkMail($token))->content();

        $this->assertStringContainsString(
            'registreren/bevestig/' . $token->token,
            $content->with['url']
        );
    }

    #[Test]
    public function content_uses_reset_route_for_password_reset_token(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'password_reset');

        $content = (new MagicLinkMail($token))->content();

        $this->assertStringContainsString(
            'wachtwoord-magic-reset/' . $token->token,
            $content->with['url']
        );
    }

    #[Test]
    public function content_passes_name_from_metadata(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register', ['name' => 'Henk']);

        $content = (new MagicLinkMail($token))->content();

        $this->assertSame('Henk', $content->with['name']);
    }

    #[Test]
    public function content_name_is_null_when_metadata_absent(): void
    {
        $token = MagicLinkToken::generate('a@b.nl', 'register');

        $this->assertNull((new MagicLinkMail($token))->content()->with['name']);
    }

    #[Test]
    public function content_uses_magic_link_markdown_template(): void
    {
        $token = MagicLinkToken::generate('a@b.nl');

        $this->assertSame('emails.magic-link', (new MagicLinkMail($token))->content()->markdown);
    }

    #[Test]
    public function content_advertises_15_minute_expiry(): void
    {
        $token = MagicLinkToken::generate('a@b.nl');

        $this->assertSame(15, (new MagicLinkMail($token))->content()->with['expiresIn']);
    }
}

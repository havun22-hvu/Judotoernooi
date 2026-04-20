<?php

namespace Tests\Feature;

use App\Models\AuthDevice;
use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function organisator(array $overrides = []): Organisator
    {
        return Organisator::factory()->create(array_merge([
            'password' => Hash::make('oldpw'),
        ], $overrides));
    }

    #[Test]
    public function unauthenticated_visit_to_account_page_is_blocked(): void
    {
        // De default unauthenticated handler op JT-routes geeft 401 (json/api
        // contract); web-routes mogen redirect zijn. Beide zijn "geweigerd".
        $response = $this->get(route('auth.account'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    #[Test]
    public function show_renders_account_view_with_active_devices_only(): void
    {
        $org = $this->organisator();
        $active = AuthDevice::create([
            'organisator_id' => $org->id, 'token' => 'a',
            'device_name' => 'Active', 'is_active' => true,
            'last_used_at' => now(),
        ]);
        AuthDevice::create([
            'organisator_id' => $org->id, 'token' => 'b',
            'device_name' => 'Inactive', 'is_active' => false,
        ]);

        $response = $this->actingAs($org, 'organisator')->get(route('auth.account'));

        $response->assertOk()
            ->assertViewIs('organisator.account')
            ->assertViewHas('organisator')
            ->assertViewHas('devices', fn ($devices) => $devices->count() === 1
                && $devices->first()->id === $active->id
            );
    }

    #[Test]
    public function update_persists_naam_email_telefoon_and_locale(): void
    {
        $org = $this->organisator(['locale' => 'nl']);

        $this->actingAs($org, 'organisator')
            ->put(route('auth.account.update'), [
                'naam' => 'Henk',
                'email' => 'henk@new.nl',
                'telefoon' => '0612345678',
                'locale' => 'en',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertSame('Henk', $org->naam);
        $this->assertSame('henk@new.nl', $org->email);
        $this->assertSame('0612345678', $org->telefoon);
        $this->assertSame('en', $org->locale);
    }

    #[Test]
    public function update_validates_required_naam_email_locale(): void
    {
        $this->actingAs($this->organisator(), 'organisator')
            ->put(route('auth.account.update'), [])
            ->assertSessionHasErrors(['naam', 'email', 'locale']);
    }

    #[Test]
    public function update_locale_must_be_nl_or_en(): void
    {
        $this->actingAs($this->organisator(), 'organisator')
            ->put(route('auth.account.update'), [
                'naam' => 'X', 'email' => 'x@y.nl', 'locale' => 'de',
            ])
            ->assertSessionHasErrors(['locale']);
    }

    #[Test]
    public function update_email_must_be_unique(): void
    {
        $taken = $this->organisator(['email' => 'taken@x.nl']);
        $other = $this->organisator(['email' => 'other@x.nl']);

        $this->actingAs($other, 'organisator')
            ->put(route('auth.account.update'), [
                'naam' => 'X', 'email' => 'taken@x.nl', 'locale' => 'nl',
            ])
            ->assertSessionHasErrors(['email']);
    }

    #[Test]
    public function update_password_succeeds_with_valid_current_password(): void
    {
        $org = $this->organisator();

        $this->actingAs($org, 'organisator')
            ->put(route('auth.account.password'), [
                'current_password' => 'oldpw',
                'password' => 'newpw123',
                'password_confirmation' => 'newpw123',
            ])
            ->assertRedirect()
            ->assertSessionHas('password_success');

        $this->assertTrue(Hash::check('newpw123', $org->fresh()->password));
    }

    #[Test]
    public function update_password_fails_with_wrong_current_password(): void
    {
        $org = $this->organisator();
        $wrongOld = 'X';

        $this->actingAs($org, 'organisator')
            ->put(route('auth.account.password'), [
                'current_password' => $wrongOld,
                'password' => 'newpw123',
                'password_confirmation' => 'newpw123',
            ])
            ->assertSessionHasErrors(['current_password']);

        $this->assertTrue(Hash::check('oldpw', $org->fresh()->password),
            'Bestaand wachtwoord moet onveranderd zijn na faal.');
    }

    #[Test]
    public function update_password_requires_confirmation_match(): void
    {
        $this->actingAs($this->organisator(), 'organisator')
            ->put(route('auth.account.password'), [
                'current_password' => 'oldpw',
                'password' => 'A1aaaaaaa',
                'password_confirmation' => 'mismatch',
            ])
            ->assertSessionHasErrors(['password']);
    }

    #[Test]
    public function remove_device_marks_device_inactive(): void
    {
        $org = $this->organisator();
        $device = AuthDevice::create([
            'organisator_id' => $org->id, 'token' => 'tok',
            'device_name' => 'D', 'is_active' => true,
        ]);

        $this->actingAs($org, 'organisator')
            ->delete(route('auth.account.device.remove', $device->id))
            ->assertRedirect()
            ->assertSessionHas('device_success');

        $this->assertFalse((bool) $device->fresh()->is_active);
    }

    #[Test]
    public function remove_device_returns_error_for_unknown_id(): void
    {
        $this->actingAs($this->organisator(), 'organisator')
            ->delete(route('auth.account.device.remove', 99999))
            ->assertSessionHasErrors(['device']);
    }

    #[Test]
    public function remove_device_cannot_target_other_organisator_device(): void
    {
        $org = $this->organisator();
        $other = $this->organisator();
        $otherDevice = AuthDevice::create([
            'organisator_id' => $other->id, 'token' => 'tok',
            'device_name' => 'D', 'is_active' => true,
        ]);

        $this->actingAs($org, 'organisator')
            ->delete(route('auth.account.device.remove', $otherDevice->id))
            ->assertSessionHasErrors(['device']);

        $this->assertTrue((bool) $otherDevice->fresh()->is_active,
            'Andermans device mag NOOIT verwijderd worden.');
    }
}

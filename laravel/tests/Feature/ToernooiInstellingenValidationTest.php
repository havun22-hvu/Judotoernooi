<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validation tests for the two settings endpoints that previously accepted raw
 * input without validation (K&V form-validation gap): the per-role tournament
 * passwords and the per-blok times. They now run through ToernooiWachtwoordenRequest
 * / ToernooiBloktijdenRequest.
 */
class ToernooiInstellingenValidationTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithToernooi(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);
        $this->actingAs($org, 'organisator');

        return [$org, $toernooi];
    }

    // --- wachtwoorden ---

    #[Test]
    public function wachtwoorden_accepts_a_valid_password(): void
    {
        [, $toernooi] = $this->orgWithToernooi();

        $response = $this->put(route('toernooi.wachtwoorden', $toernooi->routeParams()), [
            'wachtwoord_admin' => 'geheim123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    #[Test]
    public function wachtwoorden_rejects_a_too_short_password(): void
    {
        [, $toernooi] = $this->orgWithToernooi();

        $response = $this->put(route('toernooi.wachtwoorden', $toernooi->routeParams()), [
            'wachtwoord_mat' => 'ab',
        ]);

        $response->assertSessionHasErrors('wachtwoord_mat');
    }

    #[Test]
    public function wachtwoorden_allows_all_empty(): void
    {
        [, $toernooi] = $this->orgWithToernooi();

        $response = $this->put(route('toernooi.wachtwoorden', $toernooi->routeParams()), []);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    // --- bloktijden ---

    #[Test]
    public function bloktijden_accepts_valid_times(): void
    {
        [, $toernooi] = $this->orgWithToernooi();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        $response = $this->put(route('toernooi.bloktijden', $toernooi->routeParams()), [
            'blokken' => [
                $blok->id => ['weging_start' => '09:00', 'weging_einde' => '09:30', 'starttijd' => '10:00'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    #[Test]
    public function bloktijden_rejects_an_invalid_time(): void
    {
        [, $toernooi] = $this->orgWithToernooi();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        $response = $this->put(route('toernooi.bloktijden', $toernooi->routeParams()), [
            'blokken' => [
                $blok->id => ['starttijd' => '25:99'],
            ],
        ]);

        $response->assertSessionHasErrors("blokken.{$blok->id}.starttijd");
    }
}

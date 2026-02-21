<?php

namespace Tests\Feature;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiWimpelAboTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // TOERNOOI CREATION WITH WIMPEL ABO
    // =========================================================================

    #[Test]
    public function wimpel_toernooi_gets_wimpel_abo_plan_type(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();
        $this->actingAs($org, 'organisator');

        $response = $this->post(route('toernooi.store', $org), [
            'naam' => 'Wimpeltoernooi Test',
            'datum' => now()->addWeek()->format('Y-m-d'),
            'is_wimpel_toernooi' => '1',
        ]);

        $response->assertRedirect();

        $toernooi = Toernooi::where('naam', 'Wimpeltoernooi Test')->first();
        $this->assertNotNull($toernooi);
        $this->assertEquals('wimpel_abo', $toernooi->plan_type);
    }

    #[Test]
    public function wimpel_toernooi_forces_punten_competitie(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();
        $this->actingAs($org, 'organisator');

        $this->post(route('toernooi.store', $org), [
            'naam' => 'Wimpel PC Test',
            'datum' => now()->addWeek()->format('Y-m-d'),
            'is_wimpel_toernooi' => '1',
        ]);

        $toernooi = Toernooi::where('naam', 'Wimpel PC Test')->first();
        $this->assertNotNull($toernooi);

        // All categories should be punten_competitie
        $systeem = $toernooi->wedstrijd_systeem;
        if (!empty($systeem)) {
            foreach ($systeem as $key => $value) {
                $this->assertEquals('punten_competitie', $value, "Category {$key} should be punten_competitie");
            }
        }
    }

    #[Test]
    public function normal_toernooi_without_wimpel_checkbox_stays_free(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();
        $this->actingAs($org, 'organisator');

        $this->post(route('toernooi.store', $org), [
            'naam' => 'Normaal Toernooi',
            'datum' => now()->addWeek()->format('Y-m-d'),
            // No is_wimpel_toernooi checkbox
        ]);

        $toernooi = Toernooi::where('naam', 'Normaal Toernooi')->first();
        $this->assertNotNull($toernooi);
        $this->assertNotEquals('wimpel_abo', $toernooi->plan_type);
    }

    #[Test]
    public function wimpel_checkbox_ignored_without_active_abo(): void
    {
        $org = Organisator::factory()->create(); // No wimpel abo
        $this->actingAs($org, 'organisator');

        $this->post(route('toernooi.store', $org), [
            'naam' => 'Fake Wimpel',
            'datum' => now()->addWeek()->format('Y-m-d'),
            'is_wimpel_toernooi' => '1',
        ]);

        $toernooi = Toernooi::where('naam', 'Fake Wimpel')->first();
        $this->assertNotNull($toernooi);
        $this->assertNotEquals('wimpel_abo', $toernooi->plan_type);
    }

    // =========================================================================
    // TOERNOOI UPDATE ENFORCEMENT
    // =========================================================================

    #[Test]
    public function wimpel_abo_toernooi_enforces_punten_competitie_on_update(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();
        $this->actingAs($org, 'organisator');

        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'plan_type' => 'wimpel_abo',
            'gewichtsklassen' => [
                'standaard' => [
                    'label' => 'Standaard',
                    'max_leeftijd' => 99,
                    'geslacht' => 'gemengd',
                    'max_kg_verschil' => 3,
                    'gewichten' => [],
                ],
            ],
            'wedstrijd_systeem' => ['standaard' => 'punten_competitie'],
        ]);
        $org->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        // Try to update with poules system (should be overridden)
        $response = $this->put(route('toernooi.update', [$org, $toernooi]), [
            'naam' => $toernooi->naam,
            'datum' => $toernooi->datum->format('Y-m-d'),
            'wedstrijd_systeem' => ['standaard' => 'poules'],
        ]);

        $toernooi->refresh();
        $this->assertEquals('punten_competitie', $toernooi->wedstrijd_systeem['standaard'] ?? null);
    }

    // =========================================================================
    // ADMIN KLANT EDIT
    // =========================================================================

    #[Test]
    public function admin_can_activate_wimpel_abo(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $klant = Organisator::factory()->create();
        $this->actingAs($admin, 'organisator');

        $response = $this->put(route('admin.klanten.update', $klant), [
            'naam' => $klant->naam,
            'email' => $klant->email,
            'wimpel_abo_actief' => '1',
            'wimpel_abo_prijs' => '50.00',
        ]);

        $response->assertRedirect(route('admin.klanten'));

        $klant->refresh();
        $this->assertTrue($klant->wimpel_abo_actief);
        $this->assertNotNull($klant->wimpel_abo_start);
        $this->assertNotNull($klant->wimpel_abo_einde);
        $this->assertEquals('50.00', $klant->wimpel_abo_prijs);
    }

    #[Test]
    public function admin_auto_fills_dates_on_activation(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $klant = Organisator::factory()->create();
        $this->actingAs($admin, 'organisator');

        $this->put(route('admin.klanten.update', $klant), [
            'naam' => $klant->naam,
            'email' => $klant->email,
            'wimpel_abo_actief' => '1',
            // No dates provided — should auto-fill
        ]);

        $klant->refresh();
        $this->assertEquals(now()->toDateString(), $klant->wimpel_abo_start->toDateString());
        $this->assertEquals(now()->addYear()->toDateString(), $klant->wimpel_abo_einde->toDateString());
    }
}

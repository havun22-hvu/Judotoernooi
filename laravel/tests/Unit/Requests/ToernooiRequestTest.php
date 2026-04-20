<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ToernooiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new ToernooiRequest;
        $request->merge($data);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);

        // Run prepareForValidation via reflection — JSON-string normalisatie hangt
        // hier vanaf en valideren-zonder-prepare zou het pad missen
        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return Validator::make($request->all(), $request->rules(), $request->messages());
    }

    #[Test]
    public function authorize_returns_true_for_all_users(): void
    {
        $this->assertTrue((new ToernooiRequest)->authorize());
    }

    #[Test]
    public function naam_and_datum_are_required(): void
    {
        $v = $this->validate([]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('naam', $v->errors()->toArray());
        $this->assertArrayHasKey('datum', $v->errors()->toArray());
    }

    #[Test]
    public function uses_dutch_message_for_required_naam(): void
    {
        $v = $this->validate(['datum' => '2026-05-01']);

        $this->assertSame(
            'De naam van het toernooi is verplicht',
            $v->errors()->first('naam')
        );
    }

    #[Test]
    public function passes_with_minimal_valid_payload(): void
    {
        $v = $this->validate(['naam' => 'Test', 'datum' => '2026-05-01']);

        $this->assertFalse($v->fails(), 'Errors: ' . json_encode($v->errors()->toArray()));
    }

    #[Test]
    public function inschrijving_deadline_must_be_before_or_equal_to_datum(): void
    {
        $v = $this->validate([
            'naam' => 'X',
            'datum' => '2026-05-01',
            'inschrijving_deadline' => '2026-05-15', // na datum
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('inschrijving_deadline', $v->errors()->toArray());
    }

    #[Test]
    public function poule_grootte_voorkeur_string_is_decoded_from_json(): void
    {
        $v = $this->validate([
            'naam' => 'X',
            'datum' => '2026-05-01',
            'poule_grootte_voorkeur' => json_encode([3, 4, 5]),
        ]);

        $this->assertFalse($v->fails(), 'JSON-decode moet array opleveren die rules ok vindt.');
    }

    #[Test]
    public function verdeling_prioriteiten_only_accepts_known_values(): void
    {
        $v = $this->validate([
            'naam' => 'X',
            'datum' => '2026-05-01',
            'verdeling_prioriteiten' => ['leeftijd', 'gewicht', 'kleur_judogi'],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('verdeling_prioriteiten.2', $v->errors()->toArray());
    }

    #[Test]
    public function aantal_brons_only_accepts_1_or_2(): void
    {
        $this->assertFalse(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'aantal_brons' => 1])->fails()
        );
        $this->assertTrue(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'aantal_brons' => 3])->fails()
        );
    }

    #[Test]
    public function eliminatie_type_must_be_dubbel_or_ijf(): void
    {
        $this->assertFalse(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'eliminatie_type' => 'dubbel'])->fails()
        );
        $this->assertTrue(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'eliminatie_type' => 'single'])->fails()
        );
    }

    #[Test]
    public function verwacht_aantal_judokas_enforces_min_max(): void
    {
        $this->assertTrue(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'verwacht_aantal_judokas' => 5])->fails()
        );
        $this->assertTrue(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'verwacht_aantal_judokas' => 3000])->fails()
        );
        $this->assertFalse(
            $this->validate(['naam' => 'X', 'datum' => '2026-05-01', 'verwacht_aantal_judokas' => 100])->fails()
        );
    }
}

<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ClubRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new ClubRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    #[Test]
    public function authorize_returns_true(): void
    {
        $this->assertTrue((new ClubRequest)->authorize());
    }

    #[Test]
    public function naam_is_required(): void
    {
        $v = $this->validate(['email' => 'a@b.nl']);

        $this->assertTrue($v->fails());
        $this->assertSame('De clubnaam is verplicht', $v->errors()->first('naam'));
    }

    #[Test]
    public function passes_with_only_naam(): void
    {
        $this->assertFalse($this->validate(['naam' => 'Judoschool A'])->fails());
    }

    #[Test]
    public function naam_max_255_characters(): void
    {
        $v = $this->validate(['naam' => str_repeat('x', 256)]);

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('maximaal 255', $v->errors()->first('naam'));
    }

    #[Test]
    public function email_must_be_valid_when_present(): void
    {
        $this->assertTrue($this->validate(['naam' => 'X', 'email' => 'not-an-email'])->fails());
        $this->assertFalse($this->validate(['naam' => 'X', 'email' => 'a@b.nl'])->fails());
    }

    #[Test]
    public function telefoon_accepts_dutch_formats(): void
    {
        foreach (['0612345678', '06-12345678', '+31612345678', '06 1234 5678'] as $valid) {
            $this->assertFalse(
                $this->validate(['naam' => 'X', 'telefoon' => $valid])->fails(),
                "Telefoon '{$valid}' moet geldig zijn."
            );
        }
    }

    #[Test]
    public function telefoon_rejects_foreign_or_garbage(): void
    {
        foreach (['+1234567890', 'abcdefghij', '99', '00'] as $invalid) {
            $this->assertTrue(
                $this->validate(['naam' => 'X', 'telefoon' => $invalid])->fails(),
                "Telefoon '{$invalid}' moet ongeldig zijn."
            );
        }
    }
}

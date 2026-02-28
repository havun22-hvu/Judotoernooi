<?php

namespace App\WebAuthn;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class DatabaseChallengeRepository implements WebAuthnChallengeRepository
{
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $token = base64_encode((string) $challenge->data);

        // Delete expired challenges
        DB::table('webauthn_challenges')->where('expires_at', '<', now())->delete();

        // Store the challenge
        DB::table('webauthn_challenges')->updateOrInsert(
            ['token' => $token],
            [
                'challenge_data' => serialize($challenge),
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function pull(AttestationValidation|AssertionValidation $validation): ?Challenge
    {
        $challenges = DB::table('webauthn_challenges')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($challenges as $row) {
            $challenge = unserialize($row->challenge_data);

            if ($challenge?->isValid()) {
                DB::table('webauthn_challenges')->where('token', $row->token)->delete();
                return $challenge;
            }
        }

        Log::warning('WebAuthn no valid challenge found');
        return null;
    }
}

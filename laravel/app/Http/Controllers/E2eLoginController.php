<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-only login seam for the Playwright e2e suite.
 *
 * SECURITY — this endpoint logs an organisator in WITHOUT any credential check,
 * so it must never be reachable in production. Two independent guards enforce
 * that:
 *   1. routes/e2e.php is only registered from bootstrap/app.php when the app
 *      runs in the local/testing environment AND E2E_LOGIN is truthy. In every
 *      other environment the route simply does not exist (404).
 *   2. The abort_unless() below re-checks the exact same condition at request
 *      time, so the controller is inert even if it were wired up by mistake.
 *
 * Only organisators explicitly flagged `is_test = true` can be authenticated.
 */
class E2eLoginController extends Controller
{
    public function login(Request $request): Response
    {
        abort_unless(
            app()->environment(['local', 'testing']) && env('E2E_LOGIN'),
            404
        );

        $email = $request->query('email', 'e2e@judotoernooi.test');

        $organisator = Organisator::query()
            ->where('email', $email)
            ->where('is_test', true)
            ->first();

        abort_if($organisator === null, 404, 'Geen test-organisator gevonden.');

        Auth::guard('organisator')->login($organisator);

        // /dashboard redirects to the organisator's slug-scoped dashboard.
        return redirect('/dashboard');
    }
}

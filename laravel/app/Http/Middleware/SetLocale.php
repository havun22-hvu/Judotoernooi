<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\Toernooi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = config('app.available_locales', ['nl', 'en']);

        // 1. Session has locale (manual switch) → use it
        $locale = $request->session()->get('locale');

        // 2. No session locale → detect from context
        if (!$locale) {
            $locale = $this->detectFromContext($request);

            // Store in session so subsequent requests use it
            if ($locale) {
                $request->session()->put('locale', $locale);
            }
        }

        if ($locale && in_array($locale, $availableLocales)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function detectFromContext(Request $request): ?string
    {
        // Coach portal route → club locale, fallback to toernooi locale
        $routeName = $request->route()?->getName() ?? '';
        if (str_starts_with($routeName, 'coach.portal.')) {
            $toernooiSlug = $request->route('toernooi');
            $code = $request->route('code');
            if ($toernooiSlug && $code) {
                $toernooi = Toernooi::where('slug', $toernooiSlug)->first();
                if ($toernooi) {
                    $club = $toernooi->clubs()->wherePivot('portal_code', $code)->first();
                    if ($club?->locale) {
                        return $club->locale;
                    }
                    if ($toernooi->locale) {
                        return $toernooi->locale;
                    }
                }
            }
        }

        // Toernooi route → toernooi locale
        if (str_starts_with($routeName, 'toernooi.')) {
            $toernooi = $request->route('toernooi');
            if ($toernooi instanceof Toernooi && $toernooi->locale) {
                return $toernooi->locale;
            }
        }

        // Logged in organisator → organisator locale
        if (Auth::guard('organisator')->check()) {
            $organisator = Auth::guard('organisator')->user();
            if ($organisator->locale) {
                return $organisator->locale;
            }
        }

        return null;
    }
}

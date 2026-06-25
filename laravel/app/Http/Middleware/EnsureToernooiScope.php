<?php

namespace App\Http\Middleware;

use App\Models\Toernooi;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant-isolatie op object-niveau (broken object-level authorization guard).
 *
 * De groep-middleware `CheckToernooiRol` verifieert dat de ingelogde organisator
 * toegang heeft tot het `{toernooi}` uit de URL. Maar child-route-bindings
 * (`{judoka}`, `{poule}`, `{blok}`, `{mat}`, `{toegang}`, `{coachKaart}`,
 * `{betaling}`) worden NIET op dat toernooi gescoped — Laravel resolvet ze puur
 * op id. Daardoor kan een organisator via z'n EIGEN toernooi-URL een child van
 * een ANDER toernooi raken door het id te raden.
 *
 * Deze middleware sluit dat gat generiek: voor elk geresolveerd route-model dat
 * een `toernooi_id` draagt, moet dat id matchen met het `{toernooi}` uit de URL.
 * Mismatch → 404 (niet 403, om het bestaan van vreemde objecten niet te lekken).
 * Models zonder `toernooi_id` (Club, Coach, …) worden niet geraakt: die hebben
 * hun eigen scope-mechanisme.
 *
 * Mismatch → 403, consistent met de bestaande `CheckToernooiRol`- en controller-
 * checks die dit gat op enkele routes al afdekten (deze middleware maakt het
 * uniform over álle child-bindings onder de toernooi-prefix).
 */
class EnsureToernooiScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $toernooi = $route?->parameter('toernooi');

        if ($toernooi instanceof Toernooi) {
            foreach ($route->parameters() as $param) {
                if (! $param instanceof Model || $param instanceof Toernooi) {
                    continue;
                }

                $childToernooiId = $param->getAttribute('toernooi_id');
                if ($childToernooiId !== null && (int) $childToernooiId !== (int) $toernooi->id) {
                    abort(403);
                }
            }
        }

        return $next($request);
    }
}

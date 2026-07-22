<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the @alpinejs/csp assignment rule across every Blade view.
 *
 * The CSP build allows `foo = x` (Identifier) but throws
 * "Property assignments are prohibited in the CSP build" on any assignment to a
 * member expression. x-model compiles to `<expression> = __placeholder`, so
 * `x-model="form.naam"` throws the moment the user types — on staging/prod only,
 * because the strict CSP is off in `local`. Bind nested fields through a
 * {get, set} pair instead; see docs/alpine-csp-migration.md.
 *
 * The e2e CSP specs only catch violations on page load, so they miss this class
 * of bug entirely — it needs an interaction. Hence this static check.
 *
 * DO NOT REMOVE — this regression cost the vrijwilligers, clubs, stambestand and
 * mobiel forms on staging (16-07-2026).
 */
class AlpineCspBindingTest extends TestCase
{
    #[Test]
    public function no_view_binds_x_model_to_a_nested_path(): void
    {
        $overtredingen = [];

        foreach ($this->bladeFiles() as $pad) {
            foreach (file($pad) as $nr => $regel) {
                if (preg_match('/x-model(?:\.[a-z]+)*="([a-zA-Z_$][\w$]*\.[^"]*)"/', $regel, $m)) {
                    $relatief = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $pad);
                    $overtredingen[] = sprintf('%s:%d → x-model="%s"', $relatief, $nr + 1, $m[1]);
                }
            }
        }

        $this->assertSame([], $overtredingen, implode("\n", array_merge(
            ['x-model op een genest pad werkt niet in de @alpinejs/csp build:', ''],
            $overtredingen,
            ['', 'Gebruik een getter/setter-methode: x-model="formModel(\'naam\')".',
                'Zie docs/alpine-csp-migration.md → "De assignment-regel".'],
        )));
    }

    /**
     * The @alpinejs/csp evaluator is a restricted parser that does NOT support
     * optional chaining (`?.`). An expression like `list.find(...)?.naam` throws
     * the moment it is evaluated — and because it only evaluates when the branch
     * is reached (e.g. a favorite reaching "klaar staan"), it silently kills the
     * whole render at exactly the wrong moment. Local has strict CSP off, so it
     * looks fine there.
     *
     * DO NOT REMOVE — this blanked the favorieten poule cards on prod
     * (17-07-2026) whenever a favorite was on the mat.
     */
    #[Test]
    public function no_view_uses_optional_chaining_in_an_alpine_expression(): void
    {
        $overtredingen = [];

        foreach ($this->bladeFiles() as $pad) {
            foreach (file($pad) as $nr => $regel) {
                if (preg_match('/(?:x-[\w:.-]+|:[\w-]+|@[\w.-]+)="[^"]*\?\.[^"]*"/', $regel)) {
                    $relatief = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $pad);
                    $overtredingen[] = sprintf('%s:%d', $relatief, $nr + 1);
                }
            }
        }

        $this->assertSame([], $overtredingen, implode("\n", array_merge(
            ['Optional chaining (?.) werkt niet in de @alpinejs/csp build en breekt de render:', ''],
            $overtredingen,
            ['', 'Haal de waarde op via een component-methode (JS, waar ?. wel mag),',
                'of garandeer de waarde met de omhullende x-if en laat de ?. weg.'],
        )));
    }

    /**
     * The @alpinejs/csp evaluator silently does NOT execute a compound handler that
     * chains a method call with `;` — e.g. `@change="updateJP(...); saveScore(...)"`.
     * Neither statement runs, with no console error, so the interaction just dies —
     * on staging/prod only (strict CSP is off in local).
     *
     * Bind a single wrapper method instead: `@change="updateJpEnSla(...)"`.
     *
     * DO NOT REMOVE — this silently killed poule-scoring (auto WP/JP + totals) on
     * staging (22-07-2026); the JP dropdown chained updateJP + saveScore.
     */
    #[Test]
    public function no_event_handler_chains_a_method_call_with_a_semicolon(): void
    {
        $overtredingen = [];

        foreach ($this->bladeFiles() as $pad) {
            foreach (file($pad) as $nr => $regel) {
                // Event-handler (@event / x-on:) waarvan de expressie een call bevat die
                // met `);` wordt gevolgd door nog een statement (compound met call).
                if (preg_match('/(?:@[\w.:-]+|x-on:[\w.:-]+)="[^"]*\);\s*\S[^"]*"/', $regel)) {
                    $relatief = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $pad);
                    $overtredingen[] = sprintf('%s:%d', $relatief, $nr + 1);
                }
            }
        }

        $this->assertSame([], $overtredingen, implode("\n", array_merge(
            ['Compound event-handler met een methode-call + `;` faalt stil in de @alpinejs/csp build:', ''],
            $overtredingen,
            ['', 'Vervang de compound door één wrapper-methode die de calls intern doet.',
                'Zie docs/alpine-csp-migration.md → "Compound handlers".'],
        )));
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $dir = new \RecursiveDirectoryIterator(resource_path('views'));
        $paden = [];

        foreach (new \RecursiveIteratorIterator($dir) as $bestand) {
            if ($bestand->isFile() && str_ends_with($bestand->getFilename(), '.blade.php')) {
                $paden[] = $bestand->getPathname();
            }
        }

        return $paden;
    }
}

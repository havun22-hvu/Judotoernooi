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

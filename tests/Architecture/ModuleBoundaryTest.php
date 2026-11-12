<?php

declare(strict_types=1);

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Keeps the modular monolith honest. Fails the build when:
 *
 *  1. a module, or shared code, reaches into another module's internals
 *     instead of its Contract\ namespace;
 *  2. a module's Contract\ depends on that module's own internals
 *     (contracts must stay stable and standalone);
 *  3. inner layers depend on outer ones inside a module:
 *     Domain must not use Application, Infrastructure or Http;
 *     Application must not use Infrastructure or Http.
 *
 * Only bootstrap/ (the composition root), database/ scripts and tests/
 * may name a module's internals, because they wire things together.
 *
 * Each file is read once and scanned with one regex: O(total source size).
 */
final class ModuleBoundaryTest extends TestCase
{
    private const REFERENCE = '/App\\\\Modules\\\\(\w+)\\\\(\w+)/';

    /** layer => layers it must not depend on */
    private const FORBIDDEN_LAYERS = [
        'Contract'    => ['Domain', 'Application', 'Infrastructure', 'Http'],
        'Domain'      => ['Application', 'Infrastructure', 'Http'],
        'Application' => ['Infrastructure', 'Http'],
    ];

    public function testModulesOnlyTalkThroughContracts(): void
    {
        $this->assertSame([], $this->violations());
    }

    public function testTheCheckCatchesAViolation(): void
    {
        $code = "<?php\nnamespace App\\Modules\\Board\\Application;\nuse App\\Modules\\Identity\\Infrastructure\\PdoUserRepository;\n";

        $this->assertCount(1, $this->check('src/Modules/Board/Application/X.php', $code));
        $this->assertCount(0, $this->check('src/Modules/Board/Application/X.php', str_replace('Infrastructure\\PdoUserRepository', 'Contract\\UserDirectory', $code)));
        $this->assertCount(1, $this->check('src/Modules/Board/Domain/X.php', "<?php\nuse App\\Modules\\Board\\Infrastructure\\PdoBoards;\n"));
        $this->assertCount(1, $this->check('views/board.php', "<?php /** @var \\App\\Modules\\Board\\Domain\\Board \$b */"));
    }

    /** @return list<string> */
    private function violations(): array
    {
        $violations = [];

        foreach (['src', 'views', 'routes'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . "/$dir", RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen(BASE_PATH) + 1);
                array_push($violations, ...$this->check($relative, (string) file_get_contents($file->getPathname())));
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function check(string $path, string $code): array
    {
        [$ownModule, $ownLayer] = $this->locate($path);
        preg_match_all(self::REFERENCE, $code, $matches, PREG_SET_ORDER);

        $found = [];

        foreach ($matches as [$reference, $module, $layer]) {
            $isProvider = $layer === $module . 'Module';

            if ($module !== $ownModule) {
                if ($layer !== 'Contract' && !$isProvider) {
                    $found[$reference] = "$path uses $reference (only {$module}'s Contract\\ is public)";
                }
                continue;
            }

            if (in_array($layer, self::FORBIDDEN_LAYERS[$ownLayer] ?? [], true)) {
                $found[$reference] = "$path ($ownLayer) depends on its own $layer layer: $reference";
            }
        }

        return array_values($found);
    }

    /** @return array{0: ?string, 1: ?string} module and layer of a file */
    private function locate(string $path): array
    {
        if (preg_match('#^src/Modules/(\w+)/(\w+)/#', $path, $m)) {
            return [$m[1], $m[2]];
        }

        if (preg_match('#^src/Modules/(\w+)/#', $path, $m)) {
            return [$m[1], 'Root'];
        }

        return [null, null];
    }
}

<?php

declare(strict_types=1);

/*
 | Runs the test suite:  php tests/run.php [filter]
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
$passed = $failed = $assertions = 0;

foreach ($files as $file) {
    if (!str_ends_with($file->getFilename(), 'Test.php')) {
        continue;
    }

    $class = 'Tests\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(__DIR__) + 1));
    require_once $file->getPathname();

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test') || ($filter && !str_contains("$class::$method", $filter))) {
            continue;
        }

        $test = new $class();

        try {
            $test->setUp();
            $test->$method();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failed++;
            echo "\nFAIL $class::$method\n  " . $e->getMessage() . "\n  " . $e->getFile() . ':' . $e->getLine() . "\n";
        }

        $assertions += $test->assertionCount();
    }
}

echo "\n\n" . ($failed ? "FAILED" : "OK") . " — $passed passed, $failed failed, $assertions assertions\n";
exit($failed ? 1 : 0);

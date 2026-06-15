<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../test/_helpers.php';

$tests = [];
function test(string $name, callable $fn): void { global $tests; $tests[] = [$name, $fn]; }

$stack = cms_start_stack();
register_shutdown_function(static function () use (&$stack) { if ($stack !== null) cms_stop_stack($stack); });

foreach (glob(__DIR__ . '/../test/*.admin.test.php') as $file) require $file;

$pass = 0;
$fail = 0;
foreach ($tests as [$name, $fn]) {
    try {
        cms_reset_seed_cache();
        $fn($stack);
        echo "ok - $name\n";
        $pass++;
    } catch (\Throwable $e) {
        echo "not ok - $name\n";
        echo '  ' . $e->getMessage() . "\n";
        foreach (explode("\n", $e->getTraceAsString()) as $line) {
            echo '  ' . $line . "\n";
        }
        $fail++;
    }
}
echo "\n# tests " . ($pass + $fail) . "\n# pass $pass\n# fail $fail\n";
exit($fail > 0 ? 1 : 0);

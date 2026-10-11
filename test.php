<?php

declare(strict_types=1);

/**
 * Befroosh Master Test Runner
 * Executes all domain invariant, adversarial challenge, and application use-case test batteries.
 */

echo "======================================================================\n";
echo "   BEFROOSH COMPLETE VERIFICATION TEST HARNESS (CLEAN ARCH / DDD)\n";
echo "======================================================================\n\n";

$testSuites = [
    'Domain Invariants' => __DIR__ . '/tests/test_domain_invariants.php',
    'Double-Entry Accounting Stress' => __DIR__ . '/tests/challenge_double_entry.php',
    'Order Lifecycle & State Transitions' => __DIR__ . '/tests/challenge_order_lifecycle.php',
    'Adversarial Stress Battery' => __DIR__ . '/tests/test_adversarial_domain_invariants.php',
    'Application Use-Cases & Handlers' => __DIR__ . '/tests/test_application_usecases.php',
    'Frontend Archetypes E2E (DESIGN.md)' => __DIR__ . '/test_e2e_archetypes.php',
];

$allPassed = true;
$startTime = microtime(true);

foreach ($testSuites as $name => $file) {
    echo ">>> Running Suite: {$name} (" . basename($file) . ")...\n";
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($file);
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        echo "\n[ERROR] Suite '{$name}' FAILED with exit code {$exitCode}.\n";
        $allPassed = false;
        break;
    }
    echo "\n";
}

$elapsed = round(microtime(true) - $startTime, 2);

echo "======================================================================\n";
if ($allPassed) {
    echo "  ALL VERIFICATION BATTERIES PASSED (100% CLEAN) in {$elapsed}s\n";
    echo "======================================================================\n";
    exit(0);
} else {
    echo "  TEST HARNESS DETECTED FAILURES!\n";
    echo "======================================================================\n";
    exit(1);
}

<?php

declare(strict_types=1);

// PER Coding Style 3.0 (the PHP-FIG successor to PSR-12) plus strict_types everywhere.

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
    ])
    ->setFinder($finder);

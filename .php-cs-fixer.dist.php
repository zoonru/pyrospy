<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/app', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
    ])
    ->setFinder($finder);

<?php

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', '00_DATA'])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => false,
        '@Symfony' => true,
        'single_line_throw' => false,
        'concat_space' => ['spacing' => 'one'],
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'only_if_meta',
                'method' => 'one',
                'property' => 'only_if_meta',
                'trait_import' => 'none',
                'case' => 'only_if_meta',
            ],
        ],
        'single_line_comment_spacing' => false,
        'php_unit_test_annotation' => false,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match', 'array_destructuring'],
        ],
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
    ])
    ->setFinder($finder)
    ;

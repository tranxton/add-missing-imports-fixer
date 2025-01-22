<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use Tranxton\AddMissingImportsFixer\AddMissingImportsFixer;

return (new Config())
    ->setFinder((new Finder())->in(__DIR__.'/tests/Unit'))
    ->setCacheFile(__DIR__.'/var/phpcs-fixer/')
    ->registerCustomFixers([
        new AddMissingImportsFixer(),
    ])
    ->setRiskyAllowed(true)
    ->setRules([
        'ordered_imports' => [
            'sort_algorithm' => OrderedImportsFixer::SORT_ALPHA,
            'imports_order' => [
                OrderedImportsFixer::IMPORT_TYPE_CLASS,
                OrderedImportsFixer::IMPORT_TYPE_FUNCTION,
                OrderedImportsFixer::IMPORT_TYPE_CONST,
            ]
        ],
        'global_namespace_import' => [
            'import_classes' => true,
        ],
        'single_import_per_statement' => true,
        'fully_qualified_strict_types' => true,
        'tranxton/add_missing_imports' => [
            'namespace_prefix_to_remove' => 'Tests\\Unit\\',
        ],
    ]);

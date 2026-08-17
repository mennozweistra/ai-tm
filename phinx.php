<?php

declare(strict_types=1);

// The phinx binary bootstraps the consumer's Composer autoloader before evaluating this
// config file, in both the checkout and the vendor layout (ticket 269), so
// AiToolset\AiLib\Services\SchemaChecker is already available here.
use AiToolset\AiLib\Services\SchemaChecker;

$home = getenv('HOME') ?: (getenv('USERPROFILE') ?: sys_get_temp_dir());

return [
    'paths' => [
        'migrations' => SchemaChecker::defaultMigrationsPath(),
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'sqlite',
            'name' => $home . '/.ai-tm/store.db',
            'suffix' => '',
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name' => ':memory:',
            'suffix' => '',
        ],
    ],
    'version_order' => 'creation',
];

<?php

use theaddresstechnology\DDD\Modules\Activators\FileActivator;
use theaddresstechnology\DDD\Modules\Console\Commands\ComposerUpdateCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\DeleteCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\DisableCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\DumpCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\EnableCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\FreshCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\InstallCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\LangCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\ListCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\ListCommandsCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeActionCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeCastCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeChannelCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeClassCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeEnumCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeEventProviderCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeExceptionCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeHelperCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeInertiaComponentCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeInertiaPageCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeInterfaceCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeJobCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeListenerCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeProviderCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeReplacementCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeTraitCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MakeViewCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MigrateCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\MigrateV6Command;
use theaddresstechnology\DDD\Modules\Console\Commands\ModelShowCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PruneCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PublishCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PublishConfigCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PublishInertiaCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PublishMigrationCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\PublishTranslationCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\RefreshCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\ResetCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\RollbackCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\RouteProviderCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\SeedCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\SetupCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\StatusCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\UnuseCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\UpdateCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\UpdatePhpunitCommand;
use theaddresstechnology\DDD\Modules\Console\Commands\UseCommand;
use theaddresstechnology\DDD\Modules\Scaffolder;

return [
    'paths' => [
        'modules' => base_path('src/Domain'),
        'assets' => public_path('modules'),
        'public' => public_path(),
        'generator' => Scaffolder::defaultGenerators(),
    ],

    'scan' => [
        'enabled' => false,
        'paths' => [
            base_path('vendor/*/*'),
        ],
    ],

    'cache' => [
        'enabled' => false,
        'path' => env('LARAVEL_VAPOR') ? '/tmp/ddd-modules.php' : null,
    ],

    'vapor_maintenance_mode' => env('LARAVEL_VAPOR') ? '/tmp/ddd-modules.php' : null,

    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('modules_statuses.json'),
        ],
    ],

    'activator' => 'file',

    'auto-discover' => [
        'migrations' => true,
    ],

    'commands' => [
        MakeCommand::class,
        SetupCommand::class,
        EnableCommand::class,
        DisableCommand::class,
        ListCommand::class,
        ListCommandsCommand::class,
        ModelShowCommand::class,
        UseCommand::class,
        UnuseCommand::class,
        DumpCommand::class,
        ComposerUpdateCommand::class,
        LangCommand::class,
        MigrateCommand::class,
        RollbackCommand::class,
        RefreshCommand::class,
        ResetCommand::class,
        FreshCommand::class,
        StatusCommand::class,
        SeedCommand::class,
        PruneCommand::class,
        PublishCommand::class,
        PublishMigrationCommand::class,
        PublishConfigCommand::class,
        PublishTranslationCommand::class,
        PublishInertiaCommand::class,
        UpdatePhpunitCommand::class,
        InstallCommand::class,
        UpdateCommand::class,
        DeleteCommand::class,
        MigrateV6Command::class,
        MakeActionCommand::class,
        MakeCastCommand::class,
        MakeChannelCommand::class,
        MakeClassCommand::class,
        MakeEnumCommand::class,
        MakeExceptionCommand::class,
        MakeHelperCommand::class,
        MakeInterfaceCommand::class,
        MakeTraitCommand::class,
        MakeProviderCommand::class,
        RouteProviderCommand::class,
        MakeEventProviderCommand::class,
        MakeViewCommand::class,
        MakeInertiaPageCommand::class,
        MakeInertiaComponentCommand::class,
        MakeReplacementCommand::class,
        MakeListenerCommand::class,
        MakeJobCommand::class,
    ],
];

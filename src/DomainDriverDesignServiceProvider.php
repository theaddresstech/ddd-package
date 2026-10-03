<?php

namespace theaddresstechnology\DDD;

use Illuminate\Foundation\AliasLoader;
use theaddresstechnology\DDD\Make;
use theaddresstechnology\DDD\Directory;
use theaddresstechnology\DDD\Modules\Activators\Activator;
use theaddresstechnology\DDD\Modules\Bootstrapper;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;
use theaddresstechnology\DDD\Modules\Facades\Module;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;
use Illuminate\Support\ServiceProvider;

class DomainDriverDesignServiceProvider extends ServiceProvider{


    protected $commands = [
        Directory::class,
        Make::class,
    ];

    public function boot(){
        $this->setConfigs();
        $this->setCommands();
        $this->bootModules();
    }

    public function register(){
        $this->mergeConfigFrom(__DIR__.'/../config/modules.php', 'modules');
        $this->app->singleton(Activator::class, function ($app) {
            $config = $app['config']->get('modules.activators.file');
            $class = $config['class'] ?? \theaddresstechnology\DDD\Modules\Activators\FileActivator::class;

            return new $class($config['statuses-file']);
        });
        $this->app->singleton(Repository::class, function ($app) {
            return new Repository(base_path(), $app['config']->get('modules', []), $app->make(Activator::class));
        });
        $this->app->singleton(ModuleCli::class, function ($app) {
            $config = $app['config']->get('modules', []);

            return new ModuleCli(
                $app->make(Repository::class),
                new Scaffolder($config['paths']['modules'], $config['paths']['generator'] ?? []),
                base_path(),
            );
        });
        $this->app->alias(Repository::class, 'ddd.modules');
    }

    private function setConfigs(){
        $this->publishes([
            __DIR__.'/../config/ddd.php' => config_path('ddd.php'),
            __DIR__.'/../config/modules.php' => config_path('modules.php'),
        ], 'ddd-config');

        $this->mergeConfigFrom(__DIR__.'/../config/ddd.php', 'ddd');
    }

    private function setCommands(){
        $this->commands($this->commands);
        $commands = config('modules.commands', []);
        if (is_array($commands) && $commands !== []) {
            $this->commands($commands);
        }
    }

    private function bootModules(): void
    {
        if (class_exists(AliasLoader::class)) {
            $aliases = AliasLoader::getInstance();
            $aliases->alias('DddModule', Module::class);
            if (!class_exists('Module', false)) {
                $aliases->alias('Module', Module::class);
            }
        }

        (new Bootstrapper($this->app->make(Repository::class), $this->app))->boot();
    }
}

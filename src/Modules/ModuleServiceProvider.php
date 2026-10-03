<?php

namespace theaddresstechnology\DDD\Modules;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use theaddresstechnology\DDD\Helper\SafePath;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleName = '';

    protected string $moduleAlias = '';

    protected array $commands = [];

    public function boot(): void
    {
        if ($this->moduleName === '' || !$this->app->bound(Repository::class)) {
            return;
        }

        $module = $this->app->make(Repository::class)->find($this->moduleName);
        if ($module === null || !$module->isEnabled()) {
            return;
        }

        ModuleRuntime::boot($this->app, $module);
        $this->bootCommands($module);
        $this->bootSchedule();
    }

    protected function bootViews(Module $module): void
    {
        $published = function_exists('resource_path')
            ? resource_path('views/modules')
            : $module->path().'/published-views';
        $paths = ModuleAssets::viewPaths($module, $published);
        foreach ($paths as $path) {
            $this->loadViewsFrom($path, $module->alias());
        }
    }

    protected function bootComponents(Module $module): void
    {
        $namespace = ModuleAssets::componentNamespace($module);
        if ($namespace !== null) {
            Blade::componentNamespace($namespace, $module->alias());
        }
    }

    protected function bootConfig(Module $module): void
    {
        foreach (ModuleAssets::configFiles($module) as $file => $key) {
            $this->mergeConfigFrom($file, $key);
        }
    }

    protected function bootTranslations(Module $module): void
    {
        $namespaceRegistered = false;
        foreach (ModuleAssets::translationPaths($module, $this->app->langPath('vendor')) as $path) {
            if (!$namespaceRegistered) {
                $this->loadTranslationsFrom($path, $module->alias());
                $namespaceRegistered = true;
            }
            $this->loadJsonTranslationsFrom($path);
        }
    }

    protected function bootMigrations(Module $module): void
    {
        if (config('modules.auto-discover.migrations') === false) {
            return;
        }

        $path = ModuleAssets::migrationPath($module);
        if ($path !== null) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function bootCommands(Module $module): void
    {
        $commands = $this->commands;
        $console = $module->path().'/Console';
        if (is_dir($console)) {
            foreach (glob($console.'/*.php') ?: [] as $file) {
                $class = 'Src\\Domain\\'.$module->name().'\\Console\\'.basename($file, '.php');
                if (SafePath::isQualifiedName($class)) {
                    $commands[] = $class;
                }
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }

    protected function bootSchedule(): void
    {
        if (!method_exists($this, 'configureSchedules')) {
            return;
        }

        $this->app->booted(function () {
            $this->configureSchedules($this->app->make(Schedule::class));
        });
    }
}

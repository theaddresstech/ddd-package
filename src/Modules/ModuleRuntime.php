<?php

namespace theaddresstechnology\DDD\Modules;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;

class ModuleRuntime
{
    private static array $booted = [];

    public static function boot(Application $app, Module $module): void
    {
        if (isset(self::$booted[$module->name()])) {
            return;
        }
        self::$booted[$module->name()] = true;

        $publishedViews = function_exists('resource_path') ? resource_path('views/modules') : $module->path().'/published-views';
        if ($app->bound('view')) {
            foreach (ModuleAssets::viewPaths($module, $publishedViews) as $path) {
                $app->make('view')->addNamespace($module->alias(), $path);
            }
        }

        $namespace = ModuleAssets::componentNamespace($module);
        if ($namespace !== null) {
            Blade::componentNamespace($namespace, $module->alias());
        }

        foreach (ModuleAssets::configFiles($module) as $file => $key) {
            $items = require $file;
            if (!is_array($items)) {
                continue;
            }
            $existing = $app['config']->get($key, []);
            $app['config']->set($key, array_merge($items, is_array($existing) ? $existing : []));
        }

        $publishedLang = function_exists('base_path') ? base_path('resources/lang/modules') : $module->path().'/published-lang';
        if ($app->bound('translator')) {
            foreach (ModuleAssets::translationPaths($module, $publishedLang) as $path) {
                $app->make('translator')->addNamespace($module->alias(), $path);
            }
        }

        $migrations = ModuleAssets::migrationPath($module);
        if ($migrations !== null && $app['config']->get('modules.auto-discover.migrations', true) !== false) {
            $app->afterResolving('migrator', function ($migrator) use ($migrations) {
                $migrator->path($migrations);
            });
        }
    }

    public static function flush(): void
    {
        self::$booted = [];
    }
}

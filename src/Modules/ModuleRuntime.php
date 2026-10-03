<?php

namespace theaddresstechnology\DDD\Modules;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;

class ModuleRuntime
{
    private static ?\WeakMap $booted = null;

    public static function boot(Application $app, Module $module): void
    {
        self::$booted ??= new \WeakMap();
        $booted = self::$booted[$app] ?? [];
        if (isset($booted[$module->name()])) {
            return;
        }

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

        $publishedLang = $app->langPath('vendor');
        if ($app->bound('translator')) {
            $namespaceRegistered = false;
            foreach (ModuleAssets::translationPaths($module, $publishedLang) as $path) {
                if (!$namespaceRegistered) {
                    $app->make('translator')->addNamespace($module->alias(), $path);
                    $namespaceRegistered = true;
                }
                $app->make('translator')->addJsonPath($path);
            }
        }

        $migrations = ModuleAssets::migrationPath($module);
        if ($migrations !== null && $app['config']->get('modules.auto-discover.migrations', true) !== false) {
            $app->afterResolving('migrator', function ($migrator) use ($migrations) {
                $migrator->path($migrations);
            });
        }

        if ($app->bound('router') && !$app->routesAreCached()) {
            foreach (['web', 'api'] as $type) {
                $file = $module->path('routes/'.$type.'.php');
                if (is_file($file)) {
                    $router = $app->make('router')->middleware($type);
                    if ($type === 'api') {
                        $router = $router->prefix('api');
                    }
                    $router->group($file);
                }
            }
        }
        $booted[$module->name()] = true;
        self::$booted[$app] = $booted;
    }

    public static function flush(): void
    {
        self::$booted = null;
    }
}

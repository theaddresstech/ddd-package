<?php

namespace theaddresstechnology\DDD\Modules;

use Illuminate\Contracts\Foundation\Application;
use theaddresstechnology\DDD\Helper\SafePath;

class Bootstrapper
{
    public function __construct(private Repository $modules, private Application $app)
    {
    }

    public function boot(): void
    {
        foreach ($this->modules->enabled() as $module) {
            ModuleRuntime::boot($this->app, $module);

            foreach (ModuleAssets::includedFiles($module) as $file) {
                require_once $file;
            }

            foreach ($module->providers() as $provider) {
                if (!SafePath::isQualifiedName($provider) || !class_exists($provider)) {
                    throw new \RuntimeException('Module provider cannot be autoloaded: '.$provider.'. Configure Src\\ autoload and run composer dump-autoload.');
                }
                $this->app->register($provider);
            }
        }
    }
}

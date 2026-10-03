<?php

namespace theaddresstechnology\DDD\Modules;

use Composer\Autoload\ClassLoader;
use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Modules\Activators\FileActivator;

/** Read-only checks that do not bootstrap a Laravel application or load its config. */
class UpgradeDoctor
{
    public function inspect(string $root, string $modules = 'src/Domain'): array
    {
        $checks = [];
        $add = static function (string $severity, string $code, string $message) use (&$checks): void {
            $checks[] = compact('severity', 'code', 'message');
        };
        if (version_compare(PHP_VERSION, '8.4.1', '<')) {
            $add('error', 'php', 'DDD 2 requires PHP 8.4.1 or later.');
        }
        try {
            $root = realpath($root) ?: throw new \InvalidArgumentException('Application directory does not exist.');
            $file = $root.'/composer.json';
            if (!is_file($file)) {
                throw new \InvalidArgumentException('Application composer.json was not found.');
            }
            $composer = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($composer)) {
                throw new \UnexpectedValueException('Application composer.json must contain an object.');
            }
            if (isset($composer['require']['nwidart/laravel-modules'])) {
                $add('error', 'module-runtime-conflict', 'Choose one module runtime; nwidart/laravel-modules shares commands, helpers and configuration with DDD.');
            }
            $autoload = $composer['autoload']['psr-4'] ?? [];
            if (!isset($autoload['Src\\'])) {
                $add('warning', 'src-autoload', 'Add the Src\\ => src/ PSR-4 mapping or configure equivalent per-module autoloading, then run composer dump-autoload.');
            }
            $path = SafePath::confinedRelative($root, $modules);
            if ($path === null) {
                throw new \InvalidArgumentException('The modules path must be relative and inside the application.');
            }
            $activator = new FileActivator($root.'/modules_statuses.json');
            $states = $activator->all();
            $repository = new Repository($root, ['paths' => ['modules' => $path]], $activator);
            $found = $repository->all();
            $repository->enabled();
            foreach ($states as $name => $enabled) {
                if (!isset($found[$name])) {
                    $add('warning', 'unknown-status', 'The status registry references an undiscovered module: '.$name);
                }
            }
            foreach ($found as $module) {
                foreach ($module->providers() as $provider) {
                    $exists = class_exists($provider, false);
                    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
                        $exists = $exists || (bool) $loader->findFile($provider);
                    }
                    foreach ($autoload as $prefix => $directories) {
                        if (str_starts_with($provider, $prefix)) {
                            foreach ((array) $directories as $directory) {
                                $candidate = $root.'/'.trim($directory, '/').'/'.str_replace('\\', '/', substr($provider, strlen($prefix))).'.php';
                                $exists = $exists || is_file($candidate);
                            }
                        }
                    }
                    if (!$exists) {
                        $add($module->isEnabled() ? 'error' : 'warning', 'provider-autoload', $module->name().': provider was not found through Composer mappings: '.$provider);
                    }
                }
                foreach ($module->files() as $relative) {
                    $included = SafePath::confinedRelative($module->path(), $relative);
                    if ($included === null || !is_file($included) || !str_ends_with($included, '.php')) {
                        $add('error', 'module-file', $module->name().': invalid or missing manifest file: '.$relative);
                    }
                }
            }
            if (is_file($root.'/config/lighthouse.php') || is_dir($root.'/graphql')) {
                $add('warning', 'legacy-graphql', 'Existing GraphQL application files remain. DDD 2 removes generation only; review endpoints and dependencies separately.');
            }
            if (is_file($root.'/bootstrap/cache/config.php')) {
                $add('warning', 'cached-config', 'Rebuild configuration and route caches and restart workers after deployment.');
            }
            $lockFile = $root.'/composer.lock';
            if (is_file($lockFile)) {
                $lock = json_decode(file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);
                foreach ($lock['packages'] ?? [] as $package) {
                    if ($package['name'] === 'spatie/laravel-activitylog' && version_compare(ltrim($package['version'], 'v'), '5.0.0', '<')) {
                        $add('warning', 'activitylog-v5', 'Activitylog 5 requires an application schema/data migration. Follow docs/UPGRADE_2.0.md before upgrading.');
                    }
                    if ($package['name'] === 'laravel/passport' && version_compare(ltrim($package['version'], 'v'), '13.0.0', '<')) {
                        $add('warning', 'passport-v13', 'Review Passport 13 model, client-secret and schema changes before upgrading.');
                    }
                }
            }
            $add('info', 'modules', count($found).' module manifests inspected. No application files or database were changed.');
        } catch (\Throwable $exception) {
            $add('error', 'configuration', $exception->getMessage());
        }
        $errors = count(array_filter($checks, fn ($check) => $check['severity'] === 'error'));
        $warnings = count(array_filter($checks, fn ($check) => $check['severity'] === 'warning'));
        return ['ok' => $errors === 0, 'errors' => $errors, 'warnings' => $warnings, 'checks' => $checks];
    }
}

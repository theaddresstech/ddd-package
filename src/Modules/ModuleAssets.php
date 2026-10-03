<?php

namespace theaddresstechnology\DDD\Modules;

use theaddresstechnology\DDD\Helper\SafePath;

class ModuleAssets
{
    public static function viewPaths(Module $module, string $publishedRoot): array
    {
        $paths = [];
        $local = $module->path('resources/views');
        if (is_dir($local)) {
            $paths[] = $local;
        }

        $published = rtrim($publishedRoot, '/').'/'.$module->alias();
        if (is_dir($published)) {
            $paths[] = $published;
        }

        return $paths;
    }

    public static function translationPaths(Module $module, string $publishedRoot): array
    {
        $paths = [];
        foreach (['lang', 'resources/lang'] as $relative) {
            $path = $module->path($relative);
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        $published = rtrim($publishedRoot, '/').'/'.$module->alias();
        if (is_dir($published)) {
            $paths[] = $published;
        }

        return $paths;
    }

    public static function configFiles(Module $module): array
    {
        $root = $module->path('config');
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $key = preg_replace('/\.php$/', '', $relative) ?: 'config';
            $key = str_replace('/', '.', (string) $key);
            if ($key === 'config') {
                $key = $module->alias();
            } else {
                $key = $module->alias().'.'.$key;
            }
            SafePath::confine($file->getPathname(), $module->path());
            $files[$file->getPathname()] = $key;
        }

        return $files;
    }

    public static function migrationPath(Module $module): ?string
    {
        $path = $module->path('database/migrations');

        return is_dir($path) ? $path : null;
    }

    public static function componentNamespace(Module $module): ?string
    {
        $namespace = 'Src\\Domain\\'.$module->name().'\\View\\Components';
        $directory = $module->path('View/Components');

        if (!is_dir($directory) || !SafePath::isQualifiedName($namespace)) {
            return null;
        }

        return $namespace;
    }

    public static function includedFiles(Module $module): array
    {
        $files = [];
        foreach ($module->files() as $relative) {
            if (!is_string($relative)) {
                continue;
            }
            $path = SafePath::confinedRelative($module->path(), $relative);
            if ($path !== null && is_file($path) && str_ends_with($path, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}

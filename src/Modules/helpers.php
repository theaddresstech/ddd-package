<?php

use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\ViteTags;

if (!function_exists('module')) {
    function module(?string $name = null): mixed
    {
        $modules = app(Repository::class);

        if ($name === null) {
            return $modules;
        }

        return $modules->find($name);
    }
}

if (!function_exists('module_path')) {
    function module_path(string $name, string $path = ''): string
    {
        return app(Repository::class)->findOrFail($name)->path($path);
    }
}

if (!function_exists('module_vite')) {
    function module_vite(string $name, string $entry = 'resources/assets/js/app.js'): string
    {
        $modules = app(Repository::class);
        $module = $modules->findOrFail($name);
        $public = rtrim((string) ($modules->config('paths')['assets'] ?? public_path('modules')), '/');

        return ViteTags::render($public, $module->alias(), $entry);
    }
}

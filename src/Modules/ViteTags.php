<?php

namespace theaddresstechnology\DDD\Modules;

use theaddresstechnology\DDD\Helper\SafePath;

class ViteTags
{
    public static function render(string $publicModulesPath, string $alias, string $entry): string
    {
        if (!SafePath::isIdentifier($alias) || !SafePath::isRelativePath($entry)) {
            return '';
        }

        $manifest = rtrim($publicModulesPath, '/').'/'.$alias.'/manifest.json';
        if (!is_file($manifest)) {
            return '';
        }

        $json = json_decode((string) file_get_contents($manifest), true);
        $file = $json[$entry]['file'] ?? null;
        if (!is_string($file) || !SafePath::isRelativePath($file)) {
            return '';
        }

        $src = '/modules/'.$alias.'/'.$file;

        return '<script type="module" src="'.htmlspecialchars($src, ENT_QUOTES).'"></script>';
    }
}

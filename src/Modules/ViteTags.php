<?php

namespace theaddresstechnology\DDD\Modules;

use theaddresstechnology\DDD\Helper\SafePath;

class ViteTags
{
    public static function render(string $publicModulesPath, string $alias, string $entry): string
    {
        try {
            SafePath::moduleAlias($alias);
            if (!SafePath::isRelativePath($entry)) {
                return '';
            }
            $manifest = SafePath::confine(rtrim($publicModulesPath, '/').'/'.$alias.'/manifest.json', $publicModulesPath);
            if (!is_file($manifest)) {
                return '';
            }
            $json = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        } catch (\InvalidArgumentException|\JsonException) {
            return '';
        }
        $file = $json[$entry]['file'] ?? null;
        if (!is_string($file) || !SafePath::isRelativePath($file)) {
            return '';
        }

        $css = [];
        $visited = [];
        $visit = function (string $key) use (&$visit, &$css, &$visited, $json): void {
            if (isset($visited[$key])) {
                return;
            }
            $visited[$key] = true;
            $chunk = $json[$key] ?? [];
            foreach ((array) ($chunk['imports'] ?? []) as $import) {
                if (is_string($import)) {
                    $visit($import);
                }
            }
            foreach ((array) ($chunk['css'] ?? []) as $stylesheet) {
                if (is_string($stylesheet) && SafePath::isRelativePath($stylesheet)) {
                    $css[$stylesheet] = true;
                }
            }
        };
        $visit($entry);
        $url = static fn (string $asset): string => htmlspecialchars('/modules/'.$alias.'/'.$asset, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tags = '';
        foreach (array_keys($css) as $stylesheet) {
            $tags .= '<link rel="stylesheet" href="'.$url($stylesheet).'">';
        }
        return $tags.'<script type="module" src="'.$url($file).'"></script>';
    }
}

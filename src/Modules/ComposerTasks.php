<?php

namespace theaddresstechnology\DDD\Modules;

class ComposerTasks
{
    public static function packageName(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/', $name)) {
            return null;
        }

        return $name;
    }

    public static function installArguments(string $package): array
    {
        $package = self::packageName($package);
        if ($package === null) {
            throw new \InvalidArgumentException('Invalid package name.');
        }

        return ['composer', 'require', $package, '--no-interaction'];
    }

    public static function updateArguments(?string $package = null): array
    {
        $arguments = ['composer', 'update', '--no-interaction'];
        if ($package !== null) {
            $valid = self::packageName($package);
            if ($valid === null) {
                throw new \InvalidArgumentException('Invalid package name.');
            }
            $arguments[] = $valid;
        }

        return $arguments;
    }

    public static function dumpArguments(): array
    {
        return ['composer', 'dump-autoload', '--no-interaction'];
    }

    public static function rewriteAutoload(string $composerFile, string $namespace, string $path = ''): void
    {
        if (!preg_match('/^[A-Za-z\\\\]+\\\\$/', $namespace)) {
            throw new \InvalidArgumentException('Invalid namespace.');
        }

        $json = json_decode((string) file_get_contents($composerFile), true);
        if (!is_array($json)) {
            throw new \InvalidArgumentException('Module composer.json is not valid.');
        }

        $json['autoload']['psr-4'][$namespace] = $path;
        file_put_contents($composerFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    public static function ensureMergeInclude(string $composerFile, string $include): bool
    {
        if (!is_file($composerFile)) {
            return false;
        }

        $json = json_decode((string) file_get_contents($composerFile), true);
        if (!is_array($json)) {
            return false;
        }

        $includes = $json['extra']['merge-plugin']['include'] ?? [];
        if (!is_array($includes)) {
            $includes = [];
        }
        if (!in_array($include, $includes, true)) {
            $includes[] = $include;
        }
        $json['extra']['merge-plugin']['include'] = array_values($includes);
        file_put_contents($composerFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return true;
    }
}

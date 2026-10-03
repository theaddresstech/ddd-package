<?php

namespace theaddresstechnology\DDD\Modules;

use Illuminate\Support\Traits\Macroable;
use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Modules\Activators\Activator;

class Repository
{
    use Macroable;

    private ?array $cached = null;

    public function __construct(
        private string $basePath,
        private array $config,
        private Activator $activator,
    ) {
    }

    public function all(): array
    {
        if ($this->cached === null) {
            $this->cached = $this->discover();
            $this->writeCache($this->cached);
        }

        return $this->cached;
    }

    public function flush(): void
    {
        $this->cached = null;
    }

    public function ordered(): array
    {
        $modules = array_values($this->all());
        usort($modules, static fn (Module $left, Module $right) => $left->priority() <=> $right->priority());

        return $modules;
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->ordered(), static fn (Module $module) => $module->isEnabled()));
    }

    public function disabled(): array
    {
        return array_values(array_filter($this->ordered(), static fn (Module $module) => $module->isDisabled()));
    }

    public function find(string $name): ?Module
    {
        $class = $this->normalize($name);

        return $this->all()[$class] ?? null;
    }

    public function findOrFail(string $name): Module
    {
        $module = $this->find($name);

        if ($module === null) {
            throw new \InvalidArgumentException('Module not found.');
        }

        return $module;
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    public function isEnabled(string $name): bool
    {
        return (bool) $this->find($name)?->isEnabled();
    }

    public function isDisabled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null && $module->isDisabled();
    }

    public function enable(string $name): void
    {
        $module = $this->findOrFail($name);
        $this->activator->set($module->name(), true);
        $this->flush();
    }

    public function disable(string $name): void
    {
        $module = $this->findOrFail($name);
        $this->activator->set($module->name(), false);
        $this->flush();
    }

    public function forget(string $name): void
    {
        $module = $this->find($name);
        if ($module !== null) {
            $this->activator->delete($module->name());
        }
        $this->flush();
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function assetPath(string $name): string
    {
        $module = $this->findOrFail($name);
        $assets = rtrim((string) ($this->config['paths']['assets'] ?? $this->basePath.'/public/modules'), '/');

        return SafePath::confine($assets.'/'.$module->alias(), $assets);
    }

    public function asset(string $name, string $file): string
    {
        $relative = SafePath::confinedRelative($this->assetPath($name), $file);
        if ($relative === null) {
            throw new \InvalidArgumentException('Invalid asset path.');
        }

        $public = rtrim(str_replace('\\', '/', (string) ($this->config['paths']['public'] ?? $this->basePath.'/public')), '/');
        $urlPath = substr(str_replace('\\', '/', $relative), strlen($public));

        return '/'.ltrim($urlPath, '/');
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function modulesPath(): string
    {
        return (string) ($this->config['paths']['modules'] ?? $this->basePath.'/src/Domain');
    }

    public function deleteDirectory(string $name): void
    {
        $module = $this->findOrFail($name);
        $path = $module->path();
        SafePath::confine($path, $this->modulesPath());
        if (realpath($path) === realpath($this->modulesPath())) {
            throw new \InvalidArgumentException('Refusing to delete the modules root.');
        }

        if (!is_dir($path)) {
            return;
        }

        $this->removeTree($path);
        $this->activator->delete($module->name());
        $this->flush();
    }

    private function discover(): array
    {
        $modules = [];

        foreach ($this->directories() as $directory) {
            $manifestPath = $directory.'/module.json';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }
            $name = SafePath::className((string) ($manifest['name'] ?? basename($directory)));
            if (isset($modules[$name])) {
                throw new \UnexpectedValueException('Duplicate module name: '.$name);
            }
            $status = $this->activator->get($name);
            $modules[$name] = Module::fromManifest($directory, $manifest, $status !== false);
        }

        return $modules;
    }

    private function directories(): array
    {
        $roots = [$this->modulesPath()];

        if (!empty($this->config['scan']['enabled'])) {
            foreach ($this->config['scan']['paths'] ?? [] as $pattern) {
                if (!is_string($pattern) || $pattern === '') {
                    continue;
                }
                foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $directory) {
                    $roots[] = $directory;
                }
            }
        }

        $directories = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            if (is_file($root.'/module.json')) {
                $directories[] = $root;
            }
            foreach (glob(rtrim($root, '/').'/*', GLOB_ONLYDIR) ?: [] as $child) {
                if (is_file($child.'/module.json')) {
                    $directories[] = $child;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    private function cacheFile(): ?string
    {
        $vapor = $this->config['vapor_maintenance_mode'] ?? null;
        if (is_string($vapor) && $vapor !== '') {
            return $vapor;
        }

        if (empty($this->config['cache']['enabled'])) {
            return null;
        }

        $path = $this->config['cache']['path'] ?? null;

        return is_string($path) && $path !== ''
            ? $path
            : $this->basePath.'/bootstrap/cache/ddd-modules.php';
    }

    private function writeCache(array $modules): void
    {
        $file = $this->cacheFile();
        if ($file === null) {
            return;
        }

        $payload = [];
        foreach ($modules as $module) {
            $payload[$module->name()] = [
                'path' => $module->path(),
                'enabled' => $module->isEnabled(),
                'priority' => $module->priority(),
            ];
        }

        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = tempnam($directory, '.ddd-cache-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to create module cache.');
        }
        try {
            $contents = '<?php return '.var_export($payload, true).';'.PHP_EOL;
            if (file_put_contents($temporary, $contents) !== strlen($contents)
                || !chmod($temporary, 0644) || !rename($temporary, $file)) {
                throw new \RuntimeException('Unable to replace module cache.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function normalize(string $name): string
    {
        try {
            return SafePath::className($name);
        } catch (\InvalidArgumentException) {
            return $name;
        }
    }

    private function removeTree(string $directory): void
    {
        if (is_link($directory)) {
            unlink($directory);

            return;
        }
        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}

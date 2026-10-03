<?php

namespace theaddresstechnology\DDD\Modules;

use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Modules\Activators\Activator;

class Scaffolder
{
    public function __construct(private string $modulesPath, private array $generators = [])
    {
    }

    public function make(string $rawName, bool $plain, bool $api, bool $disabled, ?Activator $activator = null): string
    {
        $name = SafePath::className($rawName);
        $alias = strtolower($name);
        $path = $this->modulesPath.'/'.$name;

        if (is_dir($path)) {
            throw new \InvalidArgumentException('Module already exists.');
        }

        $this->directory($path);
        $this->identity($name, $alias);

        $provider = $name.'ServiceProvider';
        $namespace = 'Src\\Domain\\'.$name;
        $this->file($path.'/Providers/'.$provider.'.php', $this->providerStub($namespace, $name, $alias, $provider));
        $this->file($path.'/composer.json', $this->composerStub($alias, $namespace));
        $this->putJson($path.'/module.json', $this->manifest($name, $alias, $namespace.'\\Providers\\'.$provider));

        if ($plain) {
            $this->keep($path.'/Providers');
        } else {
            $this->sharedSkeleton($path, $name, $namespace, $api);
        }

        if ($activator !== null) {
            $activator->set($name, !$disabled);
        }

        return $path;
    }

    public function identity(string $rawName, ?string $alias = null): string
    {
        $name = SafePath::className($rawName);
        $alias = $alias ?: strtolower($name);
        $path = $this->modulesPath.'/'.$name;
        $namespace = 'Src\\Domain\\'.$name;
        $this->directory($path);

        if (!is_file($path.'/module.json')) {
            $this->putJson($path.'/module.json', $this->manifest($name, $alias, $namespace.'\\Providers\\DomainServiceProvider'));
        }
        if (!is_file($path.'/composer.json')) {
            $this->file($path.'/composer.json', $this->composerStub($alias, $namespace));
        }

        return $path;
    }

    public function artifact(string $moduleName, string $kind, string $rawName, array $options = []): string
    {
        $module = SafePath::className($moduleName);
        $name = SafePath::className($rawName);
        $root = $this->modulesPath.'/'.$module;
        if (!is_dir($root)) {
            throw new \InvalidArgumentException('Module not found.');
        }

        $folder = $this->folder($kind);
        $namespace = 'Src\\Domain\\'.$module.'\\'.str_replace('/', '\\', $folder);
        $frontend = $options['frontend'] ?? 'vue';

        if ($kind === 'view') {
            $file = $root.'/resources/views/'.SafePath::tableName($name).'.blade.php';
            $this->file($file, "<div>\n</div>\n");
            return $file;
        }

        if ($kind === 'inertia-page' || $kind === 'inertia-component') {
            $extension = match ($frontend) {
                'react' => 'jsx',
                'svelte' => 'svelte',
                default => 'vue',
            };
            $directory = $kind === 'inertia-page' ? 'resources/js/Pages' : 'resources/js/Components';
            $file = $root.'/'.$directory.'/'.$name.'.'.$extension;
            $this->file($file, $this->frontendStub($frontend, $name));
            return $file;
        }

        $class = match ($kind) {
            'listener' => $name.'Listener',
            'job' => $name.'Job',
            'provider', 'route-provider', 'event-provider' => $name.'ServiceProvider',
            'exception' => str_ends_with($name, 'Exception') ? $name : $name.'Exception',
            default => $name,
        };

        $file = $root.'/'.$folder.'/'.$class.'.php';
        $this->file($file, $this->classStub($kind, $namespace, $class, $options));

        return $file;
    }

    public static function defaultGenerators(): array
    {
        return [
            'action' => ['path' => 'Actions', 'generate' => false],
            'cast' => ['path' => 'Casts', 'generate' => false],
            'channel' => ['path' => 'Broadcasting', 'generate' => false],
            'class' => ['path' => 'Classes', 'generate' => false],
            'enum' => ['path' => 'Enums', 'generate' => false],
            'exception' => ['path' => 'Exceptions', 'generate' => false],
            'helper' => ['path' => 'Helpers', 'generate' => false],
            'interface' => ['path' => 'Contracts', 'generate' => false],
            'trait' => ['path' => 'Traits', 'generate' => false],
            'provider' => ['path' => 'Providers', 'generate' => true],
            'route-provider' => ['path' => 'Providers', 'generate' => false],
            'event-provider' => ['path' => 'Providers', 'generate' => false],
            'listener' => ['path' => 'Listeners', 'generate' => false],
            'job' => ['path' => 'Jobs', 'generate' => false],
            'replacement' => ['path' => 'Replacements', 'generate' => false],
            'view' => ['path' => 'resources/views', 'generate' => true],
            'inertia-page' => ['path' => 'resources/js/Pages', 'generate' => false],
            'inertia-component' => ['path' => 'resources/js/Components', 'generate' => false],
        ];
    }

    private function sharedSkeleton(string $path, string $name, string $namespace, bool $api): void
    {
        foreach ([
            'config',
            'database/migrations',
            'database/seeders',
            'database/factories',
            'lang',
            'routes',
            'tests/Feature',
            'tests/Unit',
            'View/Components',
        ] as $directory) {
            $this->keep($path.'/'.$directory);
        }

        $this->file($path.'/config/config.php', "<?php\n\nreturn [\n    'name' => '{$name}',\n];\n");
        $this->file($path.'/database/seeders/'.$name.'DatabaseSeeder.php', $this->seederStub($namespace, $name));
        $this->file($path.'/lang/en.json', "{}\n");

        if ($api) {
            return;
        }

        $this->keep($path.'/resources/assets/js');
        $this->keep($path.'/resources/assets/sass');
        $this->keep($path.'/public');
        $this->file($path.'/resources/views/index.blade.php', "<div>{$name}</div>\n");
        $this->file($path.'/resources/views/layouts/master.blade.php', "<html>\n<body>@yield('content')</body>\n</html>\n");
        $this->file($path.'/resources/assets/js/app.js', "import './bootstrap';\n");
        $this->file($path.'/resources/assets/sass/app.scss', "body {}\n");
        $this->file($path.'/package.json', $this->packageJson($name));
        $this->file($path.'/vite.config.js', $this->viteConfig($name));
    }

    private function folder(string $kind): string
    {
        return (string) ($this->generators[$kind]['path'] ?? self::defaultGenerators()[$kind]['path'] ?? 'Classes');
    }

    private function manifest(string $name, string $alias, string $provider): array
    {
        return [
            'name' => $name,
            'alias' => $alias,
            'description' => '',
            'keywords' => [],
            'priority' => 0,
            'providers' => [$provider],
            'files' => [],
        ];
    }

    private function composerStub(string $alias, string $namespace): string
    {
        return json_encode([
            'name' => 'modules/'.$alias,
            'description' => '',
            'type' => 'library',
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => '',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function providerStub(string $namespace, string $name, string $alias, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Providers;

use theaddresstechnology\\DDD\\Modules\\ModuleServiceProvider;

class {$class} extends ModuleServiceProvider
{
    protected string \$moduleName = '{$name}';

    protected string \$moduleAlias = '{$alias}';

    protected array \$commands = [];

    public function configureSchedules(\$schedule): void
    {
    }
}

PHP;
    }

    private function seederStub(string $namespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Database\\Seeders;

use Illuminate\\Database\\Seeder;

class {$name}DatabaseSeeder extends Seeder
{
    public function run(): void
    {
    }
}

PHP;
    }

    private function classStub(string $kind, string $namespace, string $class, array $options): string
    {
        $queued = !empty($options['queued']);
        $sync = !empty($options['sync']);

        return match ($kind) {
            'interface' => "<?php\n\nnamespace {$namespace};\n\ninterface {$class}\n{\n}\n",
            'trait' => "<?php\n\nnamespace {$namespace};\n\ntrait {$class}\n{\n}\n",
            'enum' => "<?php\n\nnamespace {$namespace};\n\nenum {$class}: string\n{\n    case Example = 'example';\n}\n",
            'exception' => "<?php\n\nnamespace {$namespace};\n\nclass {$class} extends \\RuntimeException\n{\n}\n",
            'listener' => $this->listenerStub($namespace, $class, $queued),
            'job' => $this->jobStub($namespace, $class, !$sync),
            'route-provider' => $this->routeProviderStub($namespace, $class),
            'event-provider' => $this->eventProviderStub($namespace, $class),
            'provider' => "<?php\n\nnamespace {$namespace};\n\nclass {$class} extends \\Illuminate\\Support\\ServiceProvider\n{\n    public function register(): void\n    {\n    }\n}\n",
            'replacement' => "<?php\n\nnamespace {$namespace};\n\nclass {$class}\n{\n    public function replace(array \$tokens): array\n    {\n        return \$tokens;\n    }\n}\n",
            default => "<?php\n\nnamespace {$namespace};\n\nclass {$class}\n{\n}\n",
        };
    }

    private function listenerStub(string $namespace, string $class, bool $queued): string
    {
        $uses = $queued ? "\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\n" : '';
        $implements = $queued ? ' implements ShouldQueue' : '';

        return <<<PHP
<?php

namespace {$namespace};
{$uses}
class {$class}{$implements}
{
    public function handle(\$event): void
    {
    }
}

PHP;
    }

    private function jobStub(string $namespace, string $class, bool $queued): string
    {
        $implements = $queued ? ' implements \\Illuminate\\Contracts\\Queue\\ShouldQueue' : '';

        return <<<PHP
<?php

namespace {$namespace};

class {$class}{$implements}
{
    public function handle(): void
    {
    }
}

PHP;
    }

    private function routeProviderStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Foundation\\Support\\Providers\\RouteServiceProvider;

class {$class} extends RouteServiceProvider
{
    public function boot(): void
    {
    }
}

PHP;
    }

    private function eventProviderStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider;

class {$class} extends EventServiceProvider
{
    protected \$listen = [];
}

PHP;
    }

    private function frontendStub(string $frontend, string $name): string
    {
        return match ($frontend) {
            'react' => "export default function {$name}() {\n  return null;\n}\n",
            'svelte' => "<script>\n</script>\n",
            default => "<script setup>\n</script>\n<template>\n</template>\n",
        };
    }

    private function packageJson(string $name): string
    {
        return json_encode([
            'name' => strtolower($name),
            'private' => true,
            'scripts' => ['build' => 'vite build'],
            'devDependencies' => ['vite' => '^6.0.0'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function viteConfig(string $name): string
    {
        $alias = strtolower($name);

        return <<<JS
import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: '../../../public/modules/{$alias}',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'resources/assets/js/app.js',
    },
  },
});

JS;
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create module directory.');
        }
    }

    private function keep(string $path): void
    {
        $this->directory($path);
        $keep = $path.'/.gitkeep';
        if (!is_file($keep)) {
            file_put_contents($keep, '');
        }
    }

    private function file(string $path, string $contents): void
    {
        $this->directory(dirname($path));
        file_put_contents($path, $contents);
    }

    private function putJson(string $path, array $data): void
    {
        $this->file($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}

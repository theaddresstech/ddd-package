<?php

namespace theaddresstechnology\DDD\Modules\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use ReflectionMethod;
use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Modules\ComposerTasks;
use theaddresstechnology\DDD\Modules\Module;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;
use theaddresstechnology\DDD\Modules\StatusMigration;

class ModuleCli
{
    public function __construct(
        private Repository $modules,
        private Scaffolder $scaffolder,
        private string $basePath,
    ) {
    }

    public function make(Command $command): int
    {
        $activator = $this->modules->config('activators');
        $file = is_array($activator) ? ($activator['file']['statuses-file'] ?? null) : null;
        $statuses = is_string($file) ? new \theaddresstechnology\DDD\Modules\Activators\FileActivator($file) : null;

        foreach ((array) $command->argument('name') as $name) {
            $path = $this->scaffolder->make(
                (string) $name,
                (bool) $command->option('plain'),
                (bool) $command->option('api'),
                (bool) $command->option('disabled'),
                $statuses,
            );
            $command->info('Created '.$path);
        }
        $this->modules->flush();

        return 0;
    }

    public function setup(Command $command): int
    {
        $path = $this->modules->modulesPath();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $statuses = $this->statusesFile();
        if (!is_file($statuses)) {
            (new \theaddresstechnology\DDD\Modules\Activators\FileActivator($statuses))->replace([]);
        }
        ComposerTasks::ensureMergeInclude($this->basePath.'/composer.json', 'src/Domain/*/composer.json');
        $command->info('Module directories are ready.');

        return 0;
    }

    public function enable(Command $command): int
    {
        $this->modules->enable((string) $command->argument('module'));
        $command->info('Module enabled.');

        return 0;
    }

    public function disable(Command $command): int
    {
        $this->modules->disable((string) $command->argument('module'));
        $command->info('Module disabled. Code and tables were left in place.');

        return 0;
    }

    public function list(Command $command): int
    {
        $rows = [];
        foreach ($this->modules->ordered() as $module) {
            $rows[] = [$module->name(), $module->isEnabled() ? 'Enabled' : 'Disabled', $module->priority()];
        }
        $command->table(['Name', 'Status', 'Priority'], $rows);

        return 0;
    }

    public function listCommands(Command $command): int
    {
        $modules = $this->selected($command);
        foreach (Artisan::all() as $name => $artisan) {
            $class = $artisan::class;
            foreach ($modules as $module) {
                if (str_starts_with($class, 'Src\\Domain\\'.$module->name().'\\')) {
                    $command->line($module->name().' '.$name);
                }
            }
        }

        return 0;
    }

    public function modelShow(Command $command): int
    {
        try {
            $module = $this->requireModule($command);
        } catch (\InvalidArgumentException $exception) {
            $command->error($exception->getMessage());

            return 1;
        }
        $model = SafePath::className((string) $command->argument('model'));
        $candidates = [
            'Src\\Domain\\'.$module->name().'\\Entities\\'.$model,
            'Src\\Domain\\'.$module->name().'\\Models\\'.$model,
        ];
        foreach ($candidates as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $instance = $reflection->newInstanceWithoutConstructor();
            $command->line('Class: '.$class);
            $command->line('Table: '.(method_exists($instance, 'getTable') ? $instance->getTable() : ''));
            $command->line('Fillable: '.implode(', ', method_exists($instance, 'getFillable') ? $instance->getFillable() : []));
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if ($type && str_contains((string) $type, 'Relation')) {
                    $command->line('Relation: '.$method->getName());
                }
            }

            return 0;
        }
        $command->error('Model was not found in the module.');

        return 1;
    }

    public function use(Command $command): int
    {
        $module = $this->modules->findOrFail((string) $command->argument('module'));
        $this->writeActive($module->name());
        $command->info('Using '.$module->name());

        return 0;
    }

    public function unuse(Command $command): int
    {
        $file = $this->activeFile();
        if (is_file($file)) {
            unlink($file);
        }
        $command->info('Cleared the active module.');

        return 0;
    }

    public function dump(Command $command): int
    {
        return $this->composer($command, ComposerTasks::dumpArguments());
    }

    public function composerUpdate(Command $command): int
    {
        try {
            $module = $this->requireModule($command);
        } catch (\InvalidArgumentException $exception) {
            $command->error($exception->getMessage());

            return 1;
        }
        ComposerTasks::rewriteAutoload(
            $module->path('composer.json'),
            'Src\\Domain\\'.$module->name().'\\'
        );
        $command->info('Updated module autoload.');

        return 0;
    }

    public function lang(Command $command): int
    {
        try {
            $module = $this->requireModule($command);
        } catch (\InvalidArgumentException $exception) {
            $command->error($exception->getMessage());

            return 1;
        }
        $files = glob($module->path().'/lang/*.php') ?: [];
        $locales = [];
        foreach ($files as $file) {
            $data = include $file;
            $locales[basename($file, '.php')] = is_array($data) ? $this->flatten($data) : [];
        }
        $all = array_unique(array_merge(...array_values($locales ?: [[]])));
        foreach ($locales as $locale => $keys) {
            $missing = array_diff($all, $keys);
            $command->line($locale.': '.(empty($missing) ? 'complete' : implode(', ', $missing)));
        }

        return 0;
    }

    public function migrate(Command $command): int
    {
        return $this->migrateCall($command, 'migrate');
    }

    public function rollback(Command $command): int
    {
        return $this->migrateCall($command, 'migrate:rollback');
    }

    public function refresh(Command $command): int
    {
        return $this->migrateCall($command, 'migrate:refresh');
    }

    public function reset(Command $command): int
    {
        return $this->migrateCall($command, 'migrate:reset');
    }

    public function fresh(Command $command): int
    {
        if (!$command->option('force')) {
            $command->error('module:migrate-fresh drops all database tables. Re-run with --force.');

            return 1;
        }

        // Resolve explicit module names before any database-changing command runs.
        $this->selected($command);
        $parameters = ['--force' => true];
        $database = $this->optional($command, 'database');
        if (is_string($database) && $database !== '') {
            $parameters['--database'] = $database;
        }
        $exit = Artisan::call('migrate:fresh', $parameters);
        $command->line(Artisan::output());
        if ($exit !== 0) {
            return $exit;
        }

        return $this->migrateCall($command, 'migrate');
    }

    public function status(Command $command): int
    {
        return $this->migrateCall($command, 'migrate:status');
    }

    public function seed(Command $command): int
    {
        foreach ($this->selected($command) as $module) {
            foreach (glob($module->path().'/database/seeders/*.php') ?: [] as $file) {
                $class = 'Src\\Domain\\'.$module->name().'\\Database\\Seeders\\'.basename($file, '.php');
                if (class_exists($class)) {
                    Artisan::call('db:seed', ['--class' => $class, '--force' => (bool) $command->option('force')]);
                    $command->line(trim(Artisan::output()));
                }
            }
        }

        return 0;
    }

    public function prune(Command $command): int
    {
        $models = [];
        foreach ($this->selected($command) as $module) {
            foreach (['Entities', 'Models'] as $folder) {
                foreach (glob($module->path().'/'.$folder.'/*.php') ?: [] as $file) {
                    $models[] = 'Src\\Domain\\'.$module->name().'\\'.$folder.'\\'.basename($file, '.php');
                }
            }
        }
        if ($models === []) {
            $command->info('No models to prune.');

            return 0;
        }
        Artisan::call('model:prune', ['--model' => $models]);
        $command->line(Artisan::output());

        return 0;
    }

    public function publish(Command $command): int
    {
        foreach ($this->selected($command) as $module) {
            $source = $module->path('public');
            if (!is_dir($source)) {
                continue;
            }
            $this->copyTree($source, $this->modules->assetPath($module->name()));
            $command->info('Published '.$module->name());
        }

        return 0;
    }

    public function publishMigration(Command $command): int
    {
        $destination = $this->basePath.'/database/migrations';
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        foreach ($this->selected($command) as $module) {
            foreach (glob($module->path('database/migrations').'/*.php') ?: [] as $file) {
                $target = $destination.'/'.date('Y_m_d_His').'_'.basename($file);
                SafePath::confine($file, $module->path());
                SafePath::confine($target, $this->basePath);
                if (is_link($file) || is_link($target)) {
                    throw new \InvalidArgumentException('Refusing to publish symbolic links.');
                }
                copy($file, $target);
            }
        }
        $command->info('Published module migrations.');

        return 0;
    }

    public function publishConfig(Command $command): int
    {
        foreach ($this->selected($command) as $module) {
            $source = $module->path('config');
            if (!is_dir($source)) {
                continue;
            }
            $target = $this->basePath.'/config/'.$module->alias();
            $this->copyTree($source, $target);
        }
        $command->info('Published module config.');

        return 0;
    }

    public function publishTranslation(Command $command): int
    {
        foreach ($this->selected($command) as $module) {
            $source = $module->path('lang');
            if (!is_dir($source)) {
                continue;
            }
            $this->copyTree($source, $this->basePath.'/resources/lang/modules/'.$module->alias());
        }
        $command->info('Published module translations.');

        return 0;
    }

    public function publishInertia(Command $command): int
    {
        $frontendOption = $this->optional($command, 'frontend');
        $frontend = is_string($frontendOption) && $frontendOption !== '' ? $frontendOption : 'vue';
        $extension = match ($frontend) {
            'react' => 'jsx',
            'svelte' => 'svelte',
            default => 'vue',
        };
        $target = SafePath::confine($this->basePath.'/resources/js/modules.js', $this->basePath);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $contents = <<<JS
const pages = import.meta.glob('../../src/Domain/*/resources/js/Pages/**/*.{$extension}');

export function resolveModulePage(name) {
  const key = Object.keys(pages).find((page) => page.endsWith('/' + name + '.{$extension}'));
  return key ? pages[key]() : Promise.reject(new Error('Page not found'));
}

JS;
        file_put_contents($target, $contents);
        $command->info('Published '.$target);

        return 0;
    }

    public function updatePhpunit(Command $command): int
    {
        $phpunit = SafePath::confine($this->basePath.'/phpunit.xml', $this->basePath);
        if (!is_file($phpunit)) {
            $command->error('phpunit.xml was not found.');

            return 1;
        }
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->load($phpunit);
        $include = $document->getElementsByTagName('include')->item(0);
        if ($include === null) {
            $source = $document->getElementsByTagName('source')->item(0);
            if ($source === null) {
                $source = $document->createElement('source');
                $document->documentElement->appendChild($source);
            }
            $include = $document->createElement('include');
            $source->appendChild($include);
        }
        foreach ($this->modules->enabled() as $module) {
            $relative = ltrim(str_replace($this->basePath, '', $module->path()), '/');
            $exists = false;
            foreach ($include->getElementsByTagName('directory') as $directory) {
                if ($directory->textContent === $relative) {
                    $exists = true;
                }
            }
            if (!$exists) {
                $node = $document->createElement('directory', $relative);
                $include->appendChild($node);
            }
        }
        $document->save($phpunit);
        $command->info('Updated phpunit.xml.');

        return 0;
    }

    public function install(Command $command): int
    {
        return $this->composer($command, ComposerTasks::installArguments((string) $command->argument('package')));
    }

    public function update(Command $command): int
    {
        $package = $command->argument('package');

        return $this->composer($command, ComposerTasks::updateArguments($package ? (string) $package : null));
    }

    public function delete(Command $command): int
    {
        if (!$command->option('force')) {
            $command->error('Re-run with --force to delete the module directory.');

            return 1;
        }
        $this->modules->deleteDirectory((string) $command->argument('module'));
        $command->info('Module deleted.');

        return 0;
    }

    public function migrateV6(Command $command): int
    {
        $legacy = $this->basePath.'/modules_statuses.php';
        $statuses = StatusMigration::convert($legacy);
        (new \theaddresstechnology\DDD\Modules\Activators\FileActivator($this->statusesFile()))->replace($statuses);
        $command->info('Wrote '.$this->statusesFile());

        return 0;
    }

    public function artifact(Command $command): int
    {
        $module = $this->moduleArgument($command);
        if ($module === null) {
            $command->error('Provide a module or run module:use first.');

            return 1;
        }
        $kind = (string) $command->option('kind');
        if ($kind === '') {
            $kind = $this->kindFromSignature($command);
        }
        $frontend = $this->optional($command, 'frontend');
        $file = $this->scaffolder->artifact($module, $kind, (string) $command->argument('name'), [
            'queued' => (bool) $this->optional($command, 'queued'),
            'sync' => (bool) $this->optional($command, 'sync'),
            'frontend' => is_string($frontend) && $frontend !== '' ? $frontend : 'vue',
        ]);
        $command->info('Created '.$file);

        return 0;
    }

    private function migrateCall(Command $command, string $artisan): int
    {
        $paths = [];
        foreach ($this->selected($command) as $module) {
            $path = $module->path('database/migrations');
            $subpath = $this->optional($command, 'subpath');
            if (is_string($subpath) && $subpath !== '') {
                $file = SafePath::confinedRelative($path, $subpath);
                if ($file === null) {
                    $command->error('Migration subpath is not inside the module.');

                    return 1;
                }
                $paths[] = $file;
                continue;
            }
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }
        foreach ($paths as $path) {
            $parameters = ['--path' => $path, '--realpath' => true];
            if ($artisan !== 'migrate:status') {
                $parameters['--force'] = (bool) $command->option('force');
            }
            $database = $this->optional($command, 'database');
            if (is_string($database) && $database !== '') {
                $parameters['--database'] = $database;
            }
            $exit = Artisan::call($artisan, $parameters);
            $command->line(trim(Artisan::output()));
            if ($exit !== 0) {
                return $exit;
            }
        }

        return 0;
    }

    private function selected(Command $command): array
    {
        $name = $this->moduleArgument($command);
        if ($name !== null) {
            return [$this->modules->findOrFail($name)];
        }

        return $this->modules->ordered();
    }

    private function requireModule(Command $command): Module
    {
        $name = $this->moduleArgument($command);
        if ($name === null) {
            throw new \InvalidArgumentException('Module is required.');
        }

        return $this->modules->findOrFail($name);
    }

    private function moduleArgument(Command $command): ?string
    {
        if ($command->getDefinition()->hasArgument('module') && $command->argument('module')) {
            return (string) $command->argument('module');
        }
        $active = $this->active();

        return $active !== '' ? $active : null;
    }

    private function kindFromSignature(Command $command): string
    {
        $name = $command->getName() ?? '';

        return match ($name) {
            'module:route-provider' => 'route-provider',
            'module:make-event-provider' => 'event-provider',
            'module:make-inertia-page' => 'inertia-page',
            'module:make-inertia-component' => 'inertia-component',
            default => str_replace('module:make-', '', $name),
        };
    }

    private function composer(Command $command, array $arguments): int
    {
        $escaped = implode(' ', array_map('escapeshellarg', $arguments));
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($escaped, $descriptor, $pipes, $this->basePath);
        if (!is_resource($process)) {
            $command->error('Unable to run Composer.');

            return 1;
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $command->line(trim($output."\n".$error));

        return $exit === 0 ? 0 : 1;
    }

    private function statusesFile(): string
    {
        $activators = $this->modules->config('activators');

        return is_array($activators) && isset($activators['file']['statuses-file'])
            ? (string) $activators['file']['statuses-file']
            : $this->basePath.'/modules_statuses.json';
    }

    private function activeFile(): string
    {
        return $this->basePath.'/storage/framework/ddd-active-module';
    }

    private function active(): string
    {
        $file = $this->activeFile();

        return is_file($file) ? trim((string) file_get_contents($file)) : '';
    }

    private function writeActive(string $name): void
    {
        $file = $this->activeFile();
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, $name);
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }

    private function optional(Command $command, string $name): mixed
    {
        if (!$command->getDefinition()->hasOption($name)) {
            return null;
        }

        return $command->option($name);
    }

    private function copyTree(string $source, string $destination): void
    {
        if (is_link($source) || is_link($destination)) {
            throw new \InvalidArgumentException('Refusing to publish symbolic links.');
        }
        SafePath::confine($destination, $this->basePath);
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep') {
                continue;
            }
            $from = $source.'/'.$item;
            $to = $destination.'/'.$item;
            if (is_link($from) || is_link($to)) {
                throw new \InvalidArgumentException('Refusing to publish symbolic links.');
            }
            SafePath::confine($to, $this->basePath);
            if (is_dir($from)) {
                $this->copyTree($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }
}

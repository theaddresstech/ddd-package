<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use theaddresstechnology\DDD\Modules\Activators\FileActivator;
use theaddresstechnology\DDD\Modules\ComposerTasks;
use theaddresstechnology\DDD\Modules\ModuleAssets;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;
use theaddresstechnology\DDD\Modules\StatusMigration;
use theaddresstechnology\DDD\Modules\ViteTags;

class ModuleSystemTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ddd-modules-'.bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_scaffold_discovers_priority_and_disable_keeps_files(): void
    {
        $modules = $this->root.'/src/Domain';
        $activator = new FileActivator($this->root.'/modules_statuses.json');
        $scaffolder = new Scaffolder($modules);
        $scaffolder->make('Blog', false, false, false, $activator);
        $scaffolder->make('Shop', true, false, true, $activator);

        $this->assertFileExists($modules.'/Blog/module.json');
        $this->assertFileExists($modules.'/Blog/composer.json');
        $this->assertFileExists($modules.'/Blog/config/config.php');
        $this->assertFileExists($modules.'/Blog/resources/views/index.blade.php');
        $this->assertFileExists($modules.'/Blog/resources/assets/js/app.js');
        $this->assertFileExists($modules.'/Blog/package.json');
        $this->assertFileExists($modules.'/Blog/vite.config.js');
        $this->assertFileExists($modules.'/Blog/tests/Feature/.gitkeep');
        $this->assertFileExists($modules.'/Blog/database/seeders/BlogDatabaseSeeder.php');
        $this->assertFileDoesNotExist($modules.'/Shop/package.json');
        $this->assertStringContainsString('configureSchedules', file_get_contents($modules.'/Blog/Providers/BlogServiceProvider.php'));

        file_put_contents($modules.'/Blog/module.json', json_encode([
            'name' => 'Blog',
            'alias' => 'blog',
            'priority' => 10,
            'providers' => [],
            'files' => [],
        ]));
        file_put_contents($modules.'/Shop/module.json', json_encode([
            'name' => 'Shop',
            'alias' => 'shop',
            'priority' => 1,
            'providers' => [],
            'files' => [],
        ]));

        $repository = $this->repository($modules, $activator);
        $ordered = array_map(static fn ($module) => $module->name(), $repository->ordered());
        $this->assertSame(['Shop', 'Blog'], $ordered);
        $this->assertTrue($repository->isEnabled('Blog'));
        $this->assertTrue($repository->isDisabled('Shop'));

        $repository->disable('Blog');
        $this->assertTrue($repository->isDisabled('Blog'));
        $this->assertFileExists($modules.'/Blog/module.json');
        $this->assertFalse($activator->get('Blog'));
    }

    public function test_api_skeleton_skips_frontend_assets(): void
    {
        $modules = $this->root.'/src/Domain';
        (new Scaffolder($modules))->make('Api', false, true, false, null);

        $this->assertFileExists($modules.'/Api/config/config.php');
        $this->assertFileDoesNotExist($modules.'/Api/vite.config.js');
        $this->assertFileDoesNotExist($modules.'/Api/resources/views/index.blade.php');
    }

    public function test_generators_cover_queued_listener_sync_job_and_react_page(): void
    {
        $modules = $this->root.'/src/Domain';
        $scaffolder = new Scaffolder($modules);
        $scaffolder->make('Blog', true, false, false, null);

        $listener = $scaffolder->artifact('Blog', 'listener', 'Notify', ['queued' => true]);
        $job = $scaffolder->artifact('Blog', 'job', 'SendMail', ['sync' => true]);
        $page = $scaffolder->artifact('Blog', 'inertia-page', 'Index', ['frontend' => 'react']);
        $enum = $scaffolder->artifact('Blog', 'enum', 'Color');

        $this->assertStringContainsString('implements ShouldQueue', file_get_contents($listener));
        $this->assertStringNotContainsString('ShouldQueue', file_get_contents($job));
        $this->assertStringContainsString('export default function Index', file_get_contents($page));
        $this->assertStringContainsString('enum Color', file_get_contents($enum));
    }

    public function test_scan_finds_extra_locations_and_delete_stays_inside_the_module_root(): void
    {
        $modules = $this->root.'/src/Domain';
        mkdir($modules, 0755, true);
        $vendor = $this->root.'/vendor/example/blog';
        mkdir($vendor, 0755, true);
        file_put_contents($vendor.'/module.json', json_encode(['name' => 'VendorBlog', 'alias' => 'vendorblog', 'priority' => 0]));

        $activator = new FileActivator($this->root.'/modules_statuses.json');
        $repository = new Repository($this->root, [
            'paths' => ['modules' => $modules, 'assets' => $this->root.'/public/modules', 'public' => $this->root.'/public'],
            'scan' => ['enabled' => true, 'paths' => [$this->root.'/vendor/*/*']],
            'cache' => ['enabled' => false],
        ], $activator);

        $this->assertTrue($repository->has('VendorBlog'));
        $this->expectException(\InvalidArgumentException::class);
        $repository->deleteDirectory('VendorBlog');
    }

    public function test_delete_removes_only_the_module_directory(): void
    {
        $modules = $this->root.'/src/Domain';
        $activator = new FileActivator($this->root.'/modules_statuses.json');
        (new Scaffolder($modules))->make('Blog', true, false, false, $activator);
        $repository = $this->repository($modules, $activator);
        $repository->deleteDirectory('Blog');

        $this->assertDirectoryDoesNotExist($modules.'/Blog');
        $this->assertNull($activator->get('Blog'));
    }

    public function test_status_migration_and_composer_tasks(): void
    {
        $legacy = $this->root.'/modules_statuses.php';
        file_put_contents($legacy, "<?php\nreturn ['modules' => ['Blog' => ['active' => true], 'Shop' => false]];\n");
        $this->assertSame(['Blog' => true, 'Shop' => false], StatusMigration::convert($legacy));

        $this->assertNull(ComposerTasks::packageName('not a package'));
        $this->assertSame(
            ['composer', 'require', 'example/blog', '--no-interaction'],
            ComposerTasks::installArguments('example/blog')
        );

        $composer = $this->root.'/composer.json';
        file_put_contents($composer, json_encode(['name' => 'app/root']));
        ComposerTasks::ensureMergeInclude($composer, 'src/Domain/*/composer.json');
        $json = json_decode(file_get_contents($composer), true);
        $this->assertSame(['src/Domain/*/composer.json'], $json['extra']['merge-plugin']['include']);

        $moduleComposer = $this->root.'/module-composer.json';
        file_put_contents($moduleComposer, json_encode(['name' => 'modules/blog']));
        ComposerTasks::rewriteAutoload($moduleComposer, 'Src\\Domain\\Blog\\');
        $moduleJson = json_decode(file_get_contents($moduleComposer), true);
        $this->assertSame('', $moduleJson['autoload']['psr-4']['Src\\Domain\\Blog\\']);
    }

    public function test_assets_config_translations_and_vite_tags(): void
    {
        $modules = $this->root.'/src/Domain';
        (new Scaffolder($modules))->make('Blog', false, false, false, null);
        mkdir($modules.'/Blog/config/nested', 0755, true);
        file_put_contents($modules.'/Blog/config/nested/app.php', "<?php\nreturn ['ok' => true];\n");
        file_put_contents($modules.'/Blog/helpers.php', "<?php\n");
        $manifest = json_decode(file_get_contents($modules.'/Blog/module.json'), true);
        $manifest['files'] = ['helpers.php', '../secrets.php'];
        file_put_contents($modules.'/Blog/module.json', json_encode($manifest));

        $activator = new FileActivator($this->root.'/modules_statuses.json');
        $module = $this->repository($modules, $activator)->findOrFail('Blog');
        $keys = array_values(ModuleAssets::configFiles($module));
        $this->assertContains('blog', $keys);
        $this->assertContains('blog.nested.app', $keys);
        $this->assertNotNull(ModuleAssets::migrationPath($module));
        $included = ModuleAssets::includedFiles($module);
        $this->assertCount(1, $included);
        $this->assertStringEndsWith('helpers.php', $included[0]);

        $public = $this->root.'/public/modules';
        mkdir($public.'/blog', 0755, true);
        file_put_contents($public.'/blog/manifest.json', json_encode([
            'resources/assets/js/app.js' => ['file' => 'assets/app.js'],
        ]));
        $html = ViteTags::render($public, 'blog', 'resources/assets/js/app.js');
        $this->assertStringContainsString('/modules/blog/assets/app.js', $html);
        $this->assertSame('', ViteTags::render($public, 'blog', '../app.js'));
    }

    private function repository(string $modules, FileActivator $activator): Repository
    {
        return new Repository($this->root, [
            'paths' => [
                'modules' => $modules,
                'assets' => $this->root.'/public/modules',
                'public' => $this->root.'/public',
            ],
            'scan' => ['enabled' => false, 'paths' => []],
            'cache' => ['enabled' => false],
        ], $activator);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.'/'.$item;
            if (is_dir($full)) {
                $this->remove($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}

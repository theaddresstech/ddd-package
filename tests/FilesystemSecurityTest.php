<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Modules\Activators\FileActivator;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;
use theaddresstechnology\DDD\Modules\Module;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;

class FilesystemSecurityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ddd-security-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/modules', 0755, true);
        mkdir($this->root.'/outside');
        file_put_contents($this->root.'/outside/secret', 'preserve');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_paths_reject_linked_ancestors_files_and_dangling_links(): void
    {
        symlink($this->root.'/outside', $this->root.'/modules/link');
        symlink($this->root.'/outside/secret', $this->root.'/modules/secret');
        symlink($this->root.'/absent', $this->root.'/modules/dangling');
        foreach (['link/secret', 'link/new/file', 'secret', 'dangling/file'] as $path) {
            $this->assertNull(SafePath::confinedRelative($this->root.'/modules', $path));
        }
        $this->assertNotNull(SafePath::confinedRelative($this->root.'/modules', 'new/file.txt'));
    }

    public function test_module_deletion_unlinks_nested_links_without_deleting_their_targets(): void
    {
        $modules = $this->root.'/modules';
        (new Scaffolder($modules))->make('Blog', true, false, false);
        symlink($this->root.'/outside', $modules.'/Blog/linked');
        $this->repository()->deleteDirectory('Blog');
        $this->assertDirectoryDoesNotExist($modules.'/Blog');
        $this->assertSame('preserve', file_get_contents($this->root.'/outside/secret'));
    }

    public function test_module_root_cannot_be_deleted_as_a_module(): void
    {
        file_put_contents($this->root.'/modules/module.json', '{"name":"Root"}');
        $this->expectException(\InvalidArgumentException::class);
        $this->repository()->deleteDirectory('Root');
    }

    public function test_scaffolder_rejects_symlinked_output(): void
    {
        $scaffolder = new Scaffolder($this->root.'/modules');
        $scaffolder->make('Blog', true, false, false);
        symlink($this->root.'/outside', $this->root.'/modules/Blog/Classes');
        try {
            $scaffolder->artifact('Blog', 'class', 'Secret');
            $this->fail('Expected confined output.');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($this->root.'/outside/Secret.php');
        }
    }

    public function test_generator_configuration_cannot_escape_its_module(): void
    {
        $scaffolder = new Scaffolder($this->root.'/modules', ['class' => ['path' => '../../outside']]);
        $scaffolder->make('Blog', true, false, false);
        $this->expectException(\InvalidArgumentException::class);
        $scaffolder->artifact('Blog', 'class', 'Secret');
    }

    public function test_manifest_aliases_reject_traversal_and_accept_normal_aliases(): void
    {
        foreach (['../outside', '/tmp', 'a/b', "a\n", ''] as $alias) {
            try {
                Module::fromManifest($this->root, ['name' => 'Blog', 'alias' => $alias], true);
                $this->fail('Unsafe alias accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('blog-news', Module::fromManifest($this->root, ['name' => 'Blog', 'alias' => 'blog-news'], true)->alias());
    }

    public function test_duplicate_module_identity_cannot_override_a_discovered_module(): void
    {
        foreach (['One', 'Two'] as $name) {
            mkdir($this->root.'/modules/'.$name);
            file_put_contents($this->root.'/modules/'.$name.'/module.json', '{"name":"Blog"}');
        }
        $this->expectException(\UnexpectedValueException::class);
        $this->repository()->all();
    }

    public function test_invalid_status_data_never_defaults_to_enabled(): void
    {
        (new Scaffolder($this->root.'/modules'))->make('Blog', true, false, false);
        foreach (['', '{', 'null', '{"Blog":"false"}', '{"Blog":0}', '[false]'] as $json) {
            file_put_contents($this->root.'/statuses.json', $json);
            try {
                $this->repository()->enabled();
                $this->fail('Invalid statuses must stop discovery.');
            } catch (\JsonException|\UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_status_updates_preserve_other_disabled_modules(): void
    {
        $a = new FileActivator($this->root.'/statuses.json');
        $b = new FileActivator($this->root.'/statuses.json');
        $a->set('Blog', false);
        $b->set('Shop', false);
        $a->set('Blog', true);
        $this->assertSame(['Blog' => true, 'Shop' => false], $b->all());
        $this->assertSame([], glob($this->root.'/.ddd-status-*'));
    }

    public function test_concurrent_status_writers_do_not_lose_disabled_modules(): void
    {
        $code = 'require $argv[1]; $activator = new \\theaddresstechnology\\DDD\\Modules\\Activators\\FileActivator($argv[2]); for ($i = 0; $i < 20; $i++) { $activator->set($argv[3].$i, false); }';
        $processes = [];
        foreach (['First', 'Second'] as $prefix) {
            $process = new \Symfony\Component\Process\Process([PHP_BINARY, '-r', $code, __DIR__.'/../vendor/autoload.php', $this->root.'/statuses.json', $prefix]);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
        }
        $statuses = (new FileActivator($this->root.'/statuses.json'))->all();
        $this->assertCount(40, $statuses);
        $this->assertSame([], array_filter($statuses));
    }

    public function test_relation_probe_does_not_follow_a_predictable_temporary_file_link(): void
    {
        $class = 'DddRelationProbe'.substr(sha1(RelationProbeFixture::class), 0, 16);
        $predictable = sys_get_temp_dir().'/'.$class.'.php';
        $target = $this->root.'/outside/secret';
        // Never overwrite a pre-existing file owned by another process.
        if (file_exists($predictable) || is_link($predictable)) {
            $this->markTestSkipped('The legacy probe path is already in use.');
        }
        symlink($target, $predictable);
        try {
            $reflection = new \ReflectionClass(\theaddresstechnology\DDD\Helper\Make\Service\Test\EntitiesRelations::class);
            $probe = $reflection->getMethod('modelUsingTrait')->invoke($reflection->newInstanceWithoutConstructor(), RelationProbeFixture::class);
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $probe);
            $this->assertSame('preserve', file_get_contents($target));
        } finally {
            unlink($predictable);
        }
    }

    public function test_publish_refuses_source_and_destination_symlinks(): void
    {
        $source = $this->root.'/modules/assets';
        mkdir($source);
        symlink($this->root.'/outside/secret', $source.'/secret');
        $cli = new ModuleCli($this->repository(), new Scaffolder($this->root.'/modules'), $this->root);
        $copy = new \ReflectionMethod($cli, 'copyTree');
        try {
            $copy->invoke($cli, $source, $this->root.'/public');
            $this->fail('Source symlinks must not be published.');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($this->root.'/public/secret');
        }
        unlink($source.'/secret');
        file_put_contents($source.'/secret', 'overwrite');
        symlink($this->root.'/outside/secret', $this->root.'/public/secret');
        try {
            $copy->invoke($cli, $source, $this->root.'/public');
            $this->fail('Destination symlinks must not be followed.');
        } catch (\InvalidArgumentException) {
            $this->assertSame('preserve', file_get_contents($this->root.'/outside/secret'));
        }
    }

    public function test_unknown_selected_module_never_falls_back_to_every_module(): void
    {
        (new Scaffolder($this->root.'/modules'))->make('Blog', true, false, false);
        $command = new class extends \Illuminate\Console\Command {
            protected $signature = 'example {module?}';
        };
        $input = new \Symfony\Component\Console\Input\ArrayInput(['module' => 'Missing'], $command->getDefinition());
        (new \ReflectionProperty($command, 'input'))->setValue($command, $input);
        $cli = new ModuleCli($this->repository(), new Scaffolder($this->root.'/modules'), $this->root);
        $this->expectException(\InvalidArgumentException::class);
        (new \ReflectionMethod($cli, 'selected'))->invoke($cli, $command);
    }

    public function test_module_cache_replaces_a_symlink_without_overwriting_its_target(): void
    {
        $cache = $this->root.'/cache.php';
        symlink($this->root.'/outside/secret', $cache);
        $repository = new Repository($this->root, [
            'paths' => ['modules' => $this->root.'/modules'],
            'cache' => ['enabled' => true, 'path' => $cache],
        ], new FileActivator($this->root.'/statuses.json'));
        $this->assertSame([], $repository->all());
        $this->assertFalse(is_link($cache));
        $this->assertSame('preserve', file_get_contents($this->root.'/outside/secret'));
        $this->assertSame([], require $cache);
    }

    public function test_module_config_loader_cannot_include_a_linked_external_php_file(): void
    {
        $path = (new Scaffolder($this->root.'/modules'))->make('Blog', false, false, false);
        file_put_contents($this->root.'/outside/external.php', '<?php return [];');
        symlink($this->root.'/outside/external.php', $path.'/config/external.php');
        $this->expectException(\InvalidArgumentException::class);
        \theaddresstechnology\DDD\Modules\ModuleAssets::configFiles($this->repository()->findOrFail('Blog'));
    }

    private function repository(): Repository
    {
        return new Repository($this->root, ['paths' => ['modules' => $this->root.'/modules']], new FileActivator($this->root.'/statuses.json'));
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path.'/'.$entry);
                }
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

trait RelationProbeFixture {}

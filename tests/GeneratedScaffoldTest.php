<?php

namespace Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use theaddresstechnology\DDD\Directory;
use theaddresstechnology\DDD\Make;

class GeneratedScaffoldTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_fresh_scaffold_generates_rest_policies_without_graphql_artifacts(): void
    {
        $base = $this->app->basePath();
        $root = sys_get_temp_dir().'/ddd-scaffold-'.bin2hex(random_bytes(8));
        $loader = new \Composer\Autoload\ClassLoader();
        $loader->addPsr4('Src\\', $root.'/src');
        $loader->register();
        try {
            foreach (['src', 'config', 'bootstrap/cache', 'database/migrations', 'database/seeders', 'routes', 'public', 'resources/views', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs'] as $directory) {
                mkdir($root.'/'.$directory, 0755, true);
            }
            file_put_contents($root.'/src/existing.php', '<?php // Existing application');
            file_put_contents($root.'/routes/console.php', '<?php');
            file_put_contents($root.'/composer.json', json_encode(['name' => 'ddd/generated-test', 'autoload' => ['psr-4' => ['Src\\' => 'src/']]]));
            $this->app->setBasePath($root);
            $this->app->usePublicPath($root.'/public');
            config(['ddd' => require __DIR__.'/../config/ddd.php']);
            $command = new Directory();
            $command->setLaravel($this->app);
            $tester = new CommandTester($command);
            $this->assertSame(1, $tester->execute([]));
            $this->assertFileExists($root.'/src/existing.php');
            $this->assertSame(0, $tester->execute(['--force' => true]), $tester->getDisplay());
            $this->assertCount(1, glob($root.'/backup/*/existing.php'));
            $this->assertFileExists($root.'/src/Domain/User/Policies/UserPolicy.php');
            $this->assertFileExists($root.'/src/Domain/User/Http/Controllers/UserController.php');
            $this->assertStringContainsString('Gate::policy(', file_get_contents($root.'/src/Domain/User/Providers/PolicyServiceProvider.php'));
            $this->assertDirectoryDoesNotExist($root.'/graphql');
            foreach (File::allFiles($root.'/src') as $file) {
                $this->assertDoesNotMatchRegularExpression('/graphql|lighthouse/i', $file->getContents());
            }
            $autoload = var_export(realpath(__DIR__.'/../vendor/autoload.php'), true);
            file_put_contents($root.'/boot-check.php', '<?php $loader = require '.$autoload.';
                $loader->addPsr4("Src\\\\", __DIR__."/src");
                $app = require __DIR__."/bootstrap/app.php";
                exit($app->handleCommand(new \\Symfony\\Component\\Console\\Input\\ArgvInput(["artisan", "route:list", "--json"])));
            ');
            $process = new \Symfony\Component\Process\Process([PHP_BINARY, $root.'/boot-check.php'], $root, [
                'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                'COMPOSER_VENDOR_DIR' => realpath(__DIR__.'/../vendor'),
            ]);
            $this->assertSame(0, $process->run(), $process->getOutput().$process->getErrorOutput());
            $routes = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertContains('api/login', array_column($routes, 'uri'));
        } finally {
            $loader->unregister();
            $this->app->setBasePath($base);
            $this->app->usePublicPath($base.'/public');
            File::deleteDirectory($root);
        }
    }

    public function test_removed_generators_are_rejected_without_exposing_their_options(): void
    {
        $command = new Make();
        $command->setLaravel($this->app);
        foreach (['graphql', 'graphqltest', 'graphtest'] as $type) {
            $tester = new CommandTester($command);
            $this->assertSame(1, $tester->execute(['type' => $type], ['interactive' => false]));
            $this->assertStringContainsString('not supported', $tester->getDisplay());
        }
        foreach ($command->getDefinition()->getOptions() as $option) {
            $this->assertStringNotContainsString('graphql', $option->getName());
        }
    }

    public function test_failed_backup_aborts_before_removing_source(): void
    {
        $base = $this->app->basePath();
        $root = sys_get_temp_dir().'/ddd-backup-'.bin2hex(random_bytes(8));
        mkdir($root.'/src', 0755, true);
        file_put_contents($root.'/src/preserve.php', 'keep');
        try {
            $this->app->setBasePath($root);
            config(['ddd' => require __DIR__.'/../config/ddd.php']);
            File::partialMock()->shouldReceive('copyDirectory')->once()->andReturn(false);
            $command = new Directory();
            $command->setLaravel($this->app);
            try {
                (new CommandTester($command))->execute(['--force' => true]);
                $this->fail('Scaffold continued after a failed backup.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Backup failed; scaffolding was not started.', $exception->getMessage());
                $this->assertSame('keep', file_get_contents($root.'/src/preserve.php'));
            }
        } finally {
            $this->app->setBasePath($base);
            File::deleteDirectory($root);
        }
    }
}

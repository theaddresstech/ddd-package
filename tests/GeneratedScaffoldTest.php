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
            foreach (['src', 'config', 'bootstrap', 'database/migrations', 'database/seeders', 'routes', 'public', 'resources/views'] as $directory) {
                mkdir($root.'/'.$directory, 0755, true);
            }
            file_put_contents($root.'/src/existing.php', '<?php // Existing application');
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

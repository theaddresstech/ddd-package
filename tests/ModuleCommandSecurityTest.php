<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use theaddresstechnology\DDD\Modules\Activators\FileActivator;
use theaddresstechnology\DDD\Modules\Console\Commands\FreshCommand;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;

class ModuleCommandSecurityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/ddd-command-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_unknown_module_is_rejected_before_fresh_can_drop_tables(): void
    {
        Artisan::swap(\Mockery::mock(\Illuminate\Contracts\Console\Kernel::class));
        Artisan::shouldReceive('call')->never();
        $command = $this->command(['module' => 'Missing', '--force' => true]);
        $cli = $this->cli();
        $this->expectException(\InvalidArgumentException::class);
        $cli->fresh($command);
    }

    public function test_fresh_honors_selected_database_and_propagates_failure(): void
    {
        Artisan::swap(\Mockery::mock(\Illuminate\Contracts\Console\Kernel::class));
        Artisan::shouldReceive('call')->once()->with('migrate:fresh', ['--force' => true, '--database' => 'testing'])->andReturn(2);
        Artisan::shouldReceive('output')->once()->andReturn('Failed');
        $command = $this->command(['--force' => true, '--database' => 'testing']);
        $this->assertSame(2, $this->cli()->fresh($command));
    }

    public function test_disabled_legacy_domain_provider_does_not_register_children_or_boot(): void
    {
        if (!class_exists(\Src\Infrastructure\AbstractProviders\ServiceProvider::class, false)) {
            require __DIR__.'/../stub/Infrastructure/AbstractProviders/ServiceProvider.stub';
        }
        $statuses = new FileActivator($this->root.'/statuses.json');
        (new Scaffolder($this->root.'/modules'))->make('Blog', true, false, true, $statuses);
        $repository = new Repository($this->root, ['paths' => ['modules' => $this->root.'/modules']], $statuses);
        $this->app->instance(Repository::class, $repository);
        $provider = new class($this->app) extends \Src\Infrastructure\AbstractProviders\ServiceProvider {
            protected ?string $moduleName = 'Blog';
            protected $providers = [MissingProviderMustNotBeLoaded::class];
            public bool $bootCalled = false;
            public function registerPolicies() { $this->bootCalled = true; }
        };
        $provider->register();
        $provider->boot();
        $this->assertFalse($provider->bootCalled);
        $this->assertTrue((new \theaddresstechnology\DDD\Helper\Make\Types\EnableDomain())->service(['domain' => 'Blog']));
        $this->assertTrue($repository->isEnabled('Blog'));
        $this->assertTrue((new \theaddresstechnology\DDD\Helper\Make\Types\DisableDomain())->service(['domain' => 'Blog']));
        $this->assertTrue($repository->isDisabled('Blog'));
    }

    private function command(array $arguments): FreshCommand
    {
        $command = \Mockery::mock(FreshCommand::class)->makePartial();
        $command->__construct();
        $command->shouldReceive('line')->zeroOrMoreTimes();
        $input = new ArrayInput($arguments, $command->getDefinition());
        (new \ReflectionProperty($command, 'input'))->setValue($command, $input);

        return $command;
    }

    private function cli(): ModuleCli
    {
        $modules = new Repository($this->root, ['paths' => ['modules' => $this->root.'/modules']], new FileActivator($this->root.'/statuses.json'));

        return new ModuleCli($modules, new Scaffolder($this->root.'/modules'), $this->root);
    }
}

<?php

namespace Tests;

use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use theaddresstechnology\DDD\DomainDriverDesignServiceProvider;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;
use theaddresstechnology\DDD\Modules\Repository;

class PackageBootCompatibilityTest extends TestCase
{
    private string $root;
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->root = sys_get_temp_dir().'/ddd-cached-upgrade-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/bootstrap/cache', 0755, true);
        file_put_contents($this->root.'/bootstrap/cache/config.php', '<?php return [];');
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        (new Filesystem())->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_package_boots_with_configuration_cached_before_modules_were_added(): void
    {
        $app = $this->application([]);
        $provider = new DomainDriverDesignServiceProvider($app);
        $provider->register();
        $provider->boot();

        $this->assertSame([], $app->make(Repository::class)->all());
        $this->assertInstanceOf(ModuleCli::class, $app->make(ModuleCli::class));
        $this->assertSame($this->root.'/src/Domain', $app['config']->get('modules.paths.modules'));
    }

    public function test_cached_application_settings_and_module_overrides_are_preserved(): void
    {
        $modulePath = $this->root.'/custom-modules';
        mkdir($modulePath.'/Blog', 0755, true);
        file_put_contents($modulePath.'/Blog/module.json', json_encode(['name' => 'Blog', 'alias' => 'blog']));
        $statusPath = $this->root.'/custom-statuses.json';
        file_put_contents($statusPath, '{"Blog":false}');
        $settings = [
            'available' => ['crm' => ['is_active' => true]],
            'paths' => ['modules' => $modulePath],
            'activators' => ['file' => ['statuses-file' => $statusPath]],
            'commands' => [],
        ];
        $app = $this->application(['modules' => $settings]);
        $provider = new DomainDriverDesignServiceProvider($app);
        $provider->register();
        $provider->boot();

        $this->assertSame([], $app->make(Repository::class)->enabled());
        $this->assertTrue($app->make(Repository::class)->isDisabled('Blog'));
        foreach ($settings as $key => $value) {
            $this->assertSame($value, $app['config']->get('modules.'.$key));
        }
    }

    public function test_cached_unrelated_modules_configuration_keeps_its_settings(): void
    {
        $settings = ['available' => ['crm' => ['is_active' => true]]];
        $app = $this->application(['modules' => $settings]);
        $provider = new DomainDriverDesignServiceProvider($app);
        $provider->register();
        $provider->boot();

        $this->assertSame($settings['available'], $app['config']->get('modules.available'));
        $this->assertSame([], $app->make(Repository::class)->all());
        $this->assertInstanceOf(ModuleCli::class, $app->make(ModuleCli::class));
    }

    private function application(array $configuration): Application
    {
        $app = new Application($this->root);
        $app->instance('config', new Config($configuration));
        $this->assertTrue($app->configurationIsCached());

        return $app;
    }
}

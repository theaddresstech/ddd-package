<?php

namespace Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use theaddresstechnology\DDD\Modules\Module;
use theaddresstechnology\DDD\Modules\ModuleRuntime;
use theaddresstechnology\DDD\Modules\Scaffolder;

class ModuleRuntimeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/ddd-module-runtime-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_routes_json_translations_and_published_views_load_once(): void
    {
        $path = (new Scaffolder($this->root.'/modules'))->make('Blog', false, false, false);
        file_put_contents($path.'/routes/api.php', '<?php \\Illuminate\\Support\\Facades\\Route::get("v2-module", fn () => ["ok" => true]);');
        file_put_contents($path.'/lang/en.json', '{"Module greeting":"Hello from module"}');
        $this->app->setBasePath($this->root);
        $this->app->useLangPath($this->root.'/lang');
        // Resolve the loader after the application path is configured.
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        mkdir($path.'/lang/en', 0755, true);
        mkdir($this->root.'/lang/vendor/blog/en', 0755, true);
        file_put_contents($path.'/lang/en/messages.php', '<?php return ["title" => "Original", "fallback" => "Still available"];');
        file_put_contents($this->root.'/lang/vendor/blog/en/messages.php', '<?php return ["title" => "Published"];');
        file_put_contents($this->root.'/lang/vendor/blog/en.json', '{"Published greeting":"Hello from published"}');
        mkdir($this->root.'/resources/views/modules/blog', 0755, true);
        file_put_contents($this->root.'/resources/views/modules/blog/index.blade.php', 'Published override');
        $module = Module::fromManifest($path, ['name' => 'Blog', 'alias' => 'blog'], true);
        ModuleRuntime::boot($this->app, $module);
        ModuleRuntime::boot($this->app, $module);
        $this->getJson('/api/v2-module')->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('Hello from module', trans('Module greeting'));
        $this->assertSame('Hello from published', trans('Published greeting'));
        $this->assertSame('Published', trans('blog::messages.title'));
        $this->assertSame('Still available', trans('blog::messages.fallback'));
        $this->assertSame('Published override', trim(view('blog::index')->render()));
    }

    public function test_boot_state_does_not_leak_between_application_instances(): void
    {
        $path = $this->root.'/Blog';
        mkdir($path.'/config', 0755, true);
        file_put_contents($path.'/config/config.php', '<?php return ["registered" => true];');
        $module = Module::fromManifest($path, ['name' => 'Blog', 'alias' => 'blog'], true);
        ModuleRuntime::boot($this->app, $module);
        $other = new \Illuminate\Foundation\Application($this->root);
        $other->instance('config', new \Illuminate\Config\Repository());
        $other->instance('files', new \Illuminate\Filesystem\Filesystem());
        try {
            ModuleRuntime::boot($other, $module);
            $this->assertTrue($this->app['config']->get('blog.registered'));
            $this->assertTrue($other['config']->get('blog.registered'));
        } finally {
            \Illuminate\Container\Container::setInstance($this->app);
        }
    }
}

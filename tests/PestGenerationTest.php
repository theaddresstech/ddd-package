<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use theaddresstechnology\DDD\Modules\Activators\Activator;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;
use theaddresstechnology\DDD\Testing\PestSetup;
use theaddresstechnology\DDD\Testing\PestTests;

uses(Tests\Support\PestApplication::class);

beforeEach(function () {
    $this->originalBase = $this->app->basePath();
    $this->pestRoot = sys_get_temp_dir().'/ddd-pest-'.bin2hex(random_bytes(8));
    mkdir($this->pestRoot, 0755, true);
    $this->app->setBasePath($this->pestRoot);
    config([
        'modules.paths.modules' => $this->pestRoot.'/src/Domain',
        'modules.activators.file.statuses-file' => $this->pestRoot.'/modules_statuses.json',
    ]);
    foreach ([Repository::class, ModuleCli::class, Activator::class] as $class) {
        $this->app->forgetInstance($class);
    }
});

afterEach(function () {
    $this->app->setBasePath($this->originalBase);
    File::deleteDirectory($this->pestRoot);
});

it('generates nested Pest unit and feature files and preserves existing tests', function () {
    $generator = new PestTests($this->pestRoot);
    $files = $generator->generate('Http/CreateOrderTest', ['Unit', 'Feature']);
    expect($files)->toHaveCount(2)
        ->and(file_get_contents($files[0]))->not->toContain('Tests\\TestCase')
        ->and(file_get_contents($files[1]))->toContain('uses(\\Tests\\TestCase::class);', '->todo()');
    file_put_contents($files[1], '<?php // Application test');
    expect(fn () => $generator->generate('Http/CreateOrderTest', ['Feature']))->toThrow(InvalidArgumentException::class);
    expect(file_get_contents($files[1]))->toBe('<?php // Application test');
    unlink($files[0]);
    expect(fn () => $generator->generate('Http/CreateOrderTest', ['Unit', 'Feature']))->toThrow(InvalidArgumentException::class);
    expect(file_exists($files[0]))->toBeFalse();
    $generator->generate('Http/CreateOrderTest', ['Unit', 'Feature'], preserveExisting: true);
    expect(is_file($files[0]))->toBeTrue()
        ->and(file_get_contents($files[1]))->toBe('<?php // Application test');
});

it('rejects unsafe names and linked output directories without external writes', function () {
    $generator = new PestTests($this->pestRoot);
    foreach (['../Escape', '/Escape', 'Nested/../Escape', 'Bad;Name', 'Bad"Name', 'Nested//Name'] as $name) {
        expect(fn () => $generator->generate($name))->toThrow(InvalidArgumentException::class);
    }
    $external = $this->pestRoot.'-external';
    mkdir($external);
    mkdir($this->pestRoot.'/tests');
    symlink($external, $this->pestRoot.'/tests/Unit');
    try {
        expect(fn () => $generator->generate('Escape', ['Unit']))->toThrow(InvalidArgumentException::class);
        expect(scandir($external))->toBe(['.', '..']);
    } finally {
        unlink($this->pestRoot.'/tests/Unit');
        rmdir($external);
    }
});

it('creates both Pest starter types for new full and API modules', function () {
    foreach (['Full' => false, 'Api' => true] as $name => $api) {
        $path = (new Scaffolder($this->pestRoot.'/src/Domain'))->make($name, false, $api, false);
        expect(is_file($path.'/tests/Unit/ExampleTest.php'))->toBeTrue()
            ->and(is_file($path.'/tests/Feature/ExampleTest.php'))->toBeTrue();
    }
});

it('exposes Pest commands with correct selection, type flags and failure codes', function () {
    (new Scaffolder($this->pestRoot.'/src/Domain'))->make('Sales', true, false, true);
    $this->artisan('module:make-test', ['name' => 'Orders/CreateOrder', 'module' => 'Sales', '--both' => true])->assertSuccessful();
    $this->artisan('module:make-test', ['name' => 'Conflict', 'module' => 'Sales', '--unit' => true, '--feature' => true])->assertFailed();
    $this->artisan('module:make-test', ['name' => 'Missing', 'module' => 'Missing'])->assertFailed();
    $this->artisan('module:make-test', ['name' => 'Orders/CreateOrder', 'module' => 'Sales'])->assertFailed();
    $this->artisan('ddd:make', ['type' => 'Test', '--domain' => 'Sales', '--name' => 'Pricing/Total', '--unit' => true])->assertSuccessful();
    $this->artisan('ddd:make', ['type' => 'Test', '--domain' => 'Sales', '--name' => 'Checkout', '--feature' => true])->assertSuccessful();
    $this->artisan('ddd:make', ['type' => 'Test', '--domain' => 'Missing', '--name' => 'Ghost'])->assertFailed();
    expect(is_file($this->pestRoot.'/src/Domain/Sales/Tests/Unit/Pricing/TotalTest.php'))->toBeTrue()
        ->and(is_file($this->pestRoot.'/src/Domain/Sales/Tests/Feature/CheckoutTest.php'))->toBeTrue()
        ->and(is_file($this->pestRoot.'/src/Domain/Sales/tests/Feature/Orders/CreateOrderTest.php'))->toBeTrue();
});

it('preserves application configuration and adds test discovery idempotently', function () {
    mkdir($this->pestRoot.'/tests');
    file_put_contents($this->pestRoot.'/tests/Pest.php', '<?php // custom Pest hooks');
    file_put_contents($this->pestRoot.'/tests/TestCase.php', '<?php // custom application base');
    file_put_contents($this->pestRoot.'/phpunit.xml.dist', '<phpunit bootstrap="custom-bootstrap.php"><!-- keep --><testsuites><testsuite name="Existing"><directory>tests</directory></testsuite></testsuites><php><env name="DB_CONNECTION" value="pgsql"/></php></phpunit>');
    $setup = new PestSetup($this->pestRoot);
    $setup->install();
    $xml = file_get_contents($this->pestRoot.'/phpunit.xml.dist');
    $setup->install();
    expect(file_get_contents($this->pestRoot.'/phpunit.xml.dist'))->toBe($xml)
        ->and($xml)->toContain('custom-bootstrap.php', '<!-- keep -->', 'pgsql', 'src/Domain/*/[Tt]ests/Unit', 'src/Domain/*/[Tt]ests/Feature')
        ->and(file_exists($this->pestRoot.'/phpunit.xml'))->toBeFalse()
        ->and(file_get_contents($this->pestRoot.'/tests/Pest.php'))->toBe('<?php // custom Pest hooks')
        ->and(file_get_contents($this->pestRoot.'/tests/TestCase.php'))->toBe('<?php // custom application base');
});

it('runs test setup through Artisan with a custom module root and returns configuration failures', function () {
    $this->artisan('ddd:setup-tests', ['--modules-path' => 'src/Modules'])->assertSuccessful();
    $xml = file_get_contents($this->pestRoot.'/phpunit.xml');
    expect($xml)->toContain('src/Modules/*/[Tt]ests/Unit', 'src/Modules/*/[Tt]ests/Feature');
    $this->artisan('ddd:setup-tests', ['--modules-path' => 'src/Modules'])->assertSuccessful();
    expect(file_get_contents($this->pestRoot.'/phpunit.xml'))->toBe($xml);
    $this->artisan('ddd:setup-tests', ['--modules-path' => '../outside'])->assertFailed();
    expect(file_get_contents($this->pestRoot.'/phpunit.xml'))->toBe($xml);
});

it('fails before modifying files when XML or setup paths are unsafe', function () {
    $setup = new PestSetup($this->pestRoot);
    file_put_contents($this->pestRoot.'/phpunit.xml', '<phpunit>');
    expect(fn () => $setup->install())->toThrow(RuntimeException::class)
        ->and(file_exists($this->pestRoot.'/tests'))->toBeFalse();
    unlink($this->pestRoot.'/phpunit.xml');
    expect(fn () => $setup->install('../outside'))->toThrow(InvalidArgumentException::class);
    mkdir($this->pestRoot.'/tests');
    file_put_contents($this->pestRoot.'/preserved.php', '<?php // preserve');
    symlink($this->pestRoot.'/preserved.php', $this->pestRoot.'/tests/Pest.php');
    expect(fn () => $setup->install())->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->pestRoot.'/preserved.php'))->toBe('<?php // preserve')
        ->and(file_exists($this->pestRoot.'/phpunit.xml'))->toBeFalse();
});

it('discovers and executes generated unit and Laravel feature tests with real Pest', function () {
    $root = $this->pestRoot;
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'src/Domain/Modern', 'src/Domain/Legacy'] as $directory) {
        mkdir($root.'/'.$directory, 0755, true);
    }
    (new PestSetup($root))->install();
    file_put_contents($root.'/composer.json', '{"name":"ddd/pest-fixture","autoload":{"psr-4":{"App\\\\":"app/"}}}');
    file_put_contents($root.'/bootstrap/providers.php', '<?php return [];');
    file_put_contents($root.'/bootstrap/app.php', <<<'PHP'
<?php
return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function () {
        Illuminate\Support\Facades\Route::get('/pest-probe', fn () => ['status' => 'ready']);
    })
    ->withMiddleware(fn ($middleware) => null)
    ->withExceptions(fn ($exceptions) => null)
    ->create();
PHP);
    $packageRoot = dirname(__DIR__);
    $autoload = var_export($packageRoot.'/vendor/autoload.php', true);
    mkdir($root.'/vendor/pestphp/pest/bin', 0755, true);
    copy($packageRoot.'/vendor/pestphp/pest/bin/pest', $root.'/vendor/pestphp/pest/bin/pest');
    file_put_contents($root.'/vendor/autoload.php', '<?php $loader = require '.$autoload.'; $loader->addPsr4("Tests\\\\", dirname(__DIR__)."/tests", true); return $loader;');
    $generated = [];
    foreach (['Modern' => 'tests', 'Legacy' => 'Tests'] as $module => $folder) {
        $generated = array_merge($generated, (new PestTests($root.'/src/Domain/'.$module))->generate('Nested/Probe', ['Unit', 'Feature'], $folder));
    }
    $process = new Process([PHP_BINARY, $root.'/vendor/pestphp/pest/bin/pest', '--colors=never', '--fail-on-warning', '--fail-on-risky', '--fail-on-deprecation', '--log-junit='.$root.'/results.xml'], $root, [
        'APP_BASE_PATH' => $root, 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'COMPOSER_VENDOR_DIR' => $packageRoot.'/vendor',
    ]);
    expect($process->run())->toBe(0, $process->getOutput().$process->getErrorOutput());
    $report = new DOMDocument();
    $report->load($root.'/results.xml');
    expect($report->getElementsByTagName('testcase')->length)->toBe(4)
        ->and($report->getElementsByTagName('skipped')->length)->toBe(4);
    foreach ($generated as $i => $file) {
        $body = str_contains($file, '/Unit/')
            ? 'expect($this)->not->toBeInstanceOf(\\Illuminate\\Foundation\\Testing\\TestCase::class); expect(2 + 2)->toBe(4);'
            : '$this->getJson("/pest-probe")->assertOk()->assertJsonPath("status", "ready");';
        $source = file_get_contents($file);
        file_put_contents($file, preg_replace('/it\([^\n]+\)->todo\(\);/', "it('executes generated test $i', function () { $body });", $source));
    }
    expect($process->run())->toBe(0, $process->getOutput().$process->getErrorOutput());
    $report->load($root.'/results.xml');
    expect($report->getElementsByTagName('testcase')->length)->toBe(4)
        ->and($report->getElementsByTagName('skipped')->length)->toBe(0)
        ->and((new DOMXPath($report))->evaluate('sum(//testcase/@assertions)'))->toBe(8.0);
});

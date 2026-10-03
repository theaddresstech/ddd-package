<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use theaddresstechnology\DDD\Modules\Activators\FileActivator;
use theaddresstechnology\DDD\Modules\Repository;
use theaddresstechnology\DDD\Modules\Scaffolder;
use theaddresstechnology\DDD\Modules\StatusMigration;
use theaddresstechnology\DDD\Modules\UpgradeDoctor;
use theaddresstechnology\DDD\Modules\ViteTags;

class ModuleV2Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ddd-v2-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0755, true);
        file_put_contents($this->root.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Src\\' => 'src/']]]));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
    }

    public function test_dependencies_boot_before_priority_and_cannot_be_disabled_or_deleted(): void
    {
        $this->module('Billing', ['requires' => ['Accounts'], 'priority' => 0]);
        $this->module('Accounts', ['priority' => 100]);
        $repository = $this->repository();
        $this->assertSame(['Accounts', 'Billing'], array_map(fn ($module) => $module->name(), $repository->enabled()));
        foreach (['disable', 'deleteDirectory'] as $method) {
            try {
                $repository->$method('Accounts');
                $this->fail('An enabled dependent lost its dependency.');
            } catch (\LogicException) {
                $this->assertDirectoryExists($this->root.'/src/Domain/Accounts');
                $this->assertTrue($repository->isEnabled('Accounts'));
            }
        }
        $repository->disable('Billing');
        $repository->disable('Accounts');
        $this->assertSame([], $repository->enabled());
        $this->expectException(\UnexpectedValueException::class);
        $repository->enable('Billing');
    }

    public function test_missing_dependency_and_cycles_fail_before_boot(): void
    {
        $this->module('Billing', ['requires' => ['Accounts']]);
        $report = (new UpgradeDoctor())->inspect($this->root);
        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('requires missing or disabled module Accounts', json_encode($report));
        $this->module('Accounts', ['requires' => ['Billing']]);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Circular module dependency');
        $this->repository()->enabled();
    }

    public function test_duplicate_alias_and_malformed_json_are_diagnosed(): void
    {
        $this->module('Blog', ['alias' => 'same']);
        $this->module('Shop', ['alias' => 'same']);
        $report = (new UpgradeDoctor())->inspect($this->root);
        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('Duplicate module alias', json_encode($report));
        file_put_contents($this->root.'/src/Domain/Shop/module.json', '{');
        $this->assertFalse((new UpgradeDoctor())->inspect($this->root)['ok']);
    }

    public function test_doctor_is_read_only_and_reports_corrupt_states_without_booting_laravel(): void
    {
        $this->module('Blog');
        $before = hash_file('sha256', $this->root.'/composer.json');
        $this->assertTrue((new UpgradeDoctor())->inspect($this->root)['ok']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/composer.json'));
        $this->assertFileDoesNotExist($this->root.'/modules_statuses.json');
        file_put_contents($this->root.'/modules_statuses.json', '{"Blog":"false"}');
        $command = new Process([PHP_BINARY, __DIR__.'/../bin/ddd-doctor', $this->root, '--json']);
        $this->assertSame(1, $command->run());
        $report = json_decode($command->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['ok']);
        $this->assertSame('{"Blog":"false"}', file_get_contents($this->root.'/modules_statuses.json'));
    }

    public function test_doctor_reports_missing_provider_autoload_and_runtime_conflicts(): void
    {
        $this->module('Blog', ['providers' => ['Src\\Domain\\Blog\\Providers\\MissingProvider']]);
        $composer = ['require' => ['nwidart/laravel-modules' => '^13.0']];
        file_put_contents($this->root.'/composer.json', json_encode($composer));
        $report = (new UpgradeDoctor())->inspect($this->root);
        $this->assertSame(2, $report['errors']);
        $this->assertContains('src-autoload', array_column($report['checks'], 'code'));
    }

    public function test_status_migration_rejects_missing_files_and_ambiguous_boolean_values(): void
    {
        foreach ([null, '<?php return ["Blog" => "false"];'] as $contents) {
            $file = $this->root.'/legacy.php';
            if ($contents !== null) {
                file_put_contents($file, $contents);
            }
            try {
                StatusMigration::convert($file);
                $this->fail('Unsafe legacy states were accepted.');
            } catch (\InvalidArgumentException|\UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_vite_renders_css_from_imports_and_handles_hyphenated_aliases_and_cycles(): void
    {
        $public = $this->root.'/public/modules';
        mkdir($public.'/my-blog', 0755, true);
        file_put_contents($public.'/my-blog/manifest.json', json_encode([
            'app.js' => ['file' => 'assets/app.js', 'imports' => ['shared.js'], 'css' => ['assets/app.css', '../secret.css']],
            'shared.js' => ['imports' => ['app.js'], 'css' => ['assets/shared.css']],
        ]));
        $html = ViteTags::render($public, 'my-blog', 'app.js');
        $this->assertStringContainsString('/modules/my-blog/assets/shared.css', $html);
        $this->assertStringContainsString('/modules/my-blog/assets/app.css', $html);
        $this->assertStringContainsString('/modules/my-blog/assets/app.js', $html);
        $this->assertStringNotContainsString('secret', $html);
        $this->assertSame('', ViteTags::render($public, '../my-blog', 'app.js'));
    }

    private function module(string $name, array $changes = []): void
    {
        $path = (new Scaffolder($this->root.'/src/Domain'))->make($name, true, false, false);
        file_put_contents($path.'/module.json', json_encode(array_replace(['name' => $name, 'alias' => strtolower($name), 'providers' => []], $changes)));
    }

    public function test_artifact_contracts_work_and_existing_code_is_preserved(): void
    {
        $scaffolder = new Scaffolder($this->root.'/src/Domain');
        $scaffolder->make('ArtifactProbe', true, false, false);
        $cast = $scaffolder->artifact('ArtifactProbe', 'cast', 'PayloadCast');
        $channel = $scaffolder->artifact('ArtifactProbe', 'channel', 'PrivateChannel');
        $job = $scaffolder->artifact('ArtifactProbe', 'job', 'SendMail');
        require_once $cast;
        require_once $channel;
        require_once $job;
        $instance = new \Src\Domain\ArtifactProbe\Casts\PayloadCast();
        $this->assertSame('value', $instance->get(new class extends \Illuminate\Database\Eloquent\Model {}, 'payload', 'value', []));
        $this->assertFalse((new \Src\Domain\ArtifactProbe\Broadcasting\PrivateChannel())->join(new \Illuminate\Foundation\Auth\User()));
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new \Src\Domain\ArtifactProbe\Jobs\SendMailJob());
        $this->assertTrue(method_exists(\Src\Domain\ArtifactProbe\Jobs\SendMailJob::class, 'dispatch'));
        file_put_contents($cast, '<?php // consumer implementation');
        try {
            $scaffolder->artifact('ArtifactProbe', 'cast', 'PayloadCast');
            $this->fail('Existing consumer code was overwritten.');
        } catch (\InvalidArgumentException) {
            $this->assertSame('<?php // consumer implementation', file_get_contents($cast));
        }
    }

    private function repository(): Repository
    {
        return new Repository($this->root, ['paths' => ['modules' => $this->root.'/src/Domain']], new FileActivator($this->root.'/modules_statuses.json'));
    }

    public function test_composer_updates_keep_numeric_namespaces_and_reject_link_targets(): void
    {
        $file = $this->root.'/module-composer.json';
        file_put_contents($file, '{"name":"modules/blog"}');
        \theaddresstechnology\DDD\Modules\ComposerTasks::rewriteAutoload($file, 'Src\\Domain\\Blog2\\');
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('Src\\Domain\\Blog2\\', $data['autoload']['psr-4']);
        symlink($file, $this->root.'/linked-composer.json');
        $before = file_get_contents($file);
        try {
            \theaddresstechnology\DDD\Modules\ComposerTasks::ensureMergeInclude($this->root.'/linked-composer.json', 'src/Domain/*/composer.json');
            $this->fail('Linked manifest was rewritten.');
        } catch (\RuntimeException) {
            $this->assertSame($before, file_get_contents($file));
        }
    }
}

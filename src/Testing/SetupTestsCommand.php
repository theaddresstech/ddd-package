<?php

namespace theaddresstechnology\DDD\Testing;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Helper\SafePath;

class SetupTestsCommand extends Command
{
    protected $signature = 'ddd:setup-tests {--modules-path= : Relative module root, defaulting to modules.paths.modules}';

    protected $description = 'Prepare Pest support and test discovery without replacing existing tests';

    public function handle(): int
    {
        try {
            $modules = $this->option('modules-path');
            if (!$modules) {
                $configured = config('modules.paths.modules', base_path('src/Domain'));
                SafePath::confine($configured, base_path());
                $modules = ltrim(substr($configured, strlen(rtrim(base_path(), '/'))), '/');
            }
            foreach ((new PestSetup(base_path()))->install($modules) as $message) {
                $this->info($message);
            }
            $this->line('Keep Tests\\ => tests/ in Composer autoload-dev and run composer dump-autoload.');
            $this->line('Install pestphp/pest in require-dev; pestphp/pest-plugin-laravel is optional. Run vendor/bin/pest.');
            return 0;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return 1;
        }
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeTestCommand extends Command
{
    protected $signature = 'module:make-test {name} {module?} {--unit} {--feature} {--both}';

    protected $description = 'Create Pest feature tests, isolated unit tests, or both';

    public function handle(ModuleCli $cli): int
    {
        return $cli->makeTest($this);
    }
}

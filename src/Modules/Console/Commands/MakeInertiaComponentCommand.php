<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeInertiaComponentCommand extends Command
{
    protected $signature = 'module:make-inertia-component {name} {module?} {--frontend=vue}';

    protected $description = 'Create an Inertia component';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

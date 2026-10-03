<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeInertiaPageCommand extends Command
{
    protected $signature = 'module:make-inertia-page {name} {module?} {--frontend=vue}';

    protected $description = 'Create an Inertia page';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

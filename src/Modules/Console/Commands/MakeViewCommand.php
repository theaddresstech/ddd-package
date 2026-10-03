<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeViewCommand extends Command
{
    protected $signature = 'module:make-view {name} {module?}';

    protected $description = 'Create a Blade view';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

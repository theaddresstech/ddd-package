<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeClassCommand extends Command
{
    protected $signature = 'module:make-class {name} {module?}';

    protected $description = 'Create a plain PHP class';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

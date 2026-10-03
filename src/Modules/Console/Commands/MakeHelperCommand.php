<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeHelperCommand extends Command
{
    protected $signature = 'module:make-helper {name} {module?}';

    protected $description = 'Create a helper class';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

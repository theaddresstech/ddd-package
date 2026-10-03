<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeReplacementCommand extends Command
{
    protected $signature = 'module:make-replacement {name} {module?}';

    protected $description = 'Create a stub replacement class';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

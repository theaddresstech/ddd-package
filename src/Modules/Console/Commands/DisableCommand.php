<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class DisableCommand extends Command
{
    protected $signature = 'module:disable {module}';

    protected $description = 'Disable a module without dropping its tables';

    public function handle(ModuleCli $cli): int
    {
        return $cli->disable($this);
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class SetupCommand extends Command
{
    protected $signature = 'module:setup';

    protected $description = 'Create the module directories the package expects';

    public function handle(ModuleCli $cli): int
    {
        return $cli->setup($this);
    }
}

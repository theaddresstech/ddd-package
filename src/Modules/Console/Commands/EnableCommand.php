<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class EnableCommand extends Command
{
    protected $signature = 'module:enable {module}';

    protected $description = 'Enable a module';

    public function handle(ModuleCli $cli): int
    {
        return $cli->enable($this);
    }
}

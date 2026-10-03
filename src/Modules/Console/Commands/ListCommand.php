<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class ListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List modules and whether each one is enabled';

    public function handle(ModuleCli $cli): int
    {
        return $cli->list($this);
    }
}

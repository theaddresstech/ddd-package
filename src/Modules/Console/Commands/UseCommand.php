<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class UseCommand extends Command
{
    protected $signature = 'module:use {module}';

    protected $description = 'Remember a module for later generators';

    public function handle(ModuleCli $cli): int
    {
        return $cli->use($this);
    }
}

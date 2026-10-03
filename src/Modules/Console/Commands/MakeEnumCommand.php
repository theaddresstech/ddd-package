<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeEnumCommand extends Command
{
    protected $signature = 'module:make-enum {name} {module?}';

    protected $description = 'Create a PHP enum';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

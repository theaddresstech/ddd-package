<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeCastCommand extends Command
{
    protected $signature = 'module:make-cast {name} {module?}';

    protected $description = 'Create an Eloquent cast';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

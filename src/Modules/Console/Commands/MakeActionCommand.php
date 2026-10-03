<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeActionCommand extends Command
{
    protected $signature = 'module:make-action {name} {module?}';

    protected $description = 'Create an action class';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

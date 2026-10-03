<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeInterfaceCommand extends Command
{
    protected $signature = 'module:make-interface {name} {module?}';

    protected $description = 'Create an interface';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeListenerCommand extends Command
{
    protected $signature = 'module:make-listener {name} {module?} {--queued}';

    protected $description = 'Create a listener';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

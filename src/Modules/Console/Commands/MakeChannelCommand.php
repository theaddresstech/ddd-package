<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeChannelCommand extends Command
{
    protected $signature = 'module:make-channel {name} {module?}';

    protected $description = 'Create a broadcast channel';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

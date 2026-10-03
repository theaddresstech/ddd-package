<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeEventProviderCommand extends Command
{
    protected $signature = 'module:make-event-provider {name} {module?}';

    protected $description = 'Create an additional event service provider';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class RouteProviderCommand extends Command
{
    protected $signature = 'module:route-provider {name} {module?}';

    protected $description = 'Create an additional route service provider';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

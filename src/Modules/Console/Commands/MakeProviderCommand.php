<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeProviderCommand extends Command
{
    protected $signature = 'module:make-provider {name} {module?}';

    protected $description = 'Create an additional service provider';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

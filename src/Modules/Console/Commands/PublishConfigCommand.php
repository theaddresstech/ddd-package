<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PublishConfigCommand extends Command
{
    protected $signature = 'module:publish-config {module?}';

    protected $description = 'Copy module config into the application';

    public function handle(ModuleCli $cli): int
    {
        return $cli->publishConfig($this);
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PublishCommand extends Command
{
    protected $signature = 'module:publish {module?}';

    protected $description = 'Copy module public assets into public/modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->publish($this);
    }
}

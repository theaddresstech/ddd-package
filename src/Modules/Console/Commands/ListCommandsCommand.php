<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class ListCommandsCommand extends Command
{
    protected $signature = 'module:list-commands {module?}';

    protected $description = 'List Artisan commands defined by modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->listCommands($this);
    }
}

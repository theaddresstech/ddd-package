<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class DeleteCommand extends Command
{
    protected $signature = 'module:delete {module} {--force}';

    protected $description = 'Delete a module directory';

    public function handle(ModuleCli $cli): int
    {
        return $cli->delete($this);
    }
}

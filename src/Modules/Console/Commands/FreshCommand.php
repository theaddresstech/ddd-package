<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class FreshCommand extends Command
{
    protected $signature = 'module:migrate-fresh {module?} {--force}';

    protected $description = 'Drop all tables and re-run module migrations';

    public function handle(ModuleCli $cli): int
    {
        return $cli->fresh($this);
    }
}

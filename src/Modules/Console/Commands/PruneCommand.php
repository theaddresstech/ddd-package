<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PruneCommand extends Command
{
    protected $signature = 'module:prune {module?}';

    protected $description = 'Prune obsolete Eloquent models for modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->prune($this);
    }
}

<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class SeedCommand extends Command
{
    protected $signature = 'module:seed {module?} {--force} {--database=}';

    protected $description = 'Run module seeders in priority order';

    public function handle(ModuleCli $cli): int
    {
        return $cli->seed($this);
    }
}

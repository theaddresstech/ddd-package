<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MigrateCommand extends Command
{
    protected $signature = 'module:migrate {module?} {--force} {--database=} {--subpath=}';

    protected $description = 'Run migrations for one module or every module';

    public function handle(ModuleCli $cli): int
    {
        return $cli->migrate($this);
    }
}

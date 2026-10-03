<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MigrateV6Command extends Command
{
    protected $signature = 'module:v6:migrate {--force : Replace an existing status registry}';

    protected $description = 'Convert legacy module status data to the status file';

    public function handle(ModuleCli $cli): int
    {
        return $cli->migrateV6($this);
    }
}

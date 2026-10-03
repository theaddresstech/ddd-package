<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class StatusCommand extends Command
{
    protected $signature = 'module:migrate-status {module?} {--database=}';

    protected $description = 'Show migration status for modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->status($this);
    }
}

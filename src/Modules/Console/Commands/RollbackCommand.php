<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class RollbackCommand extends Command
{
    protected $signature = 'module:migrate-rollback {module?} {--force} {--database=} {--subpath=}';

    protected $description = 'Roll back module migrations';

    public function handle(ModuleCli $cli): int
    {
        return $cli->rollback($this);
    }
}

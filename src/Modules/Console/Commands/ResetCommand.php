<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class ResetCommand extends Command
{
    protected $signature = 'module:migrate-reset {module?} {--force} {--database=}';

    protected $description = 'Reset module migrations';

    public function handle(ModuleCli $cli): int
    {
        return $cli->reset($this);
    }
}

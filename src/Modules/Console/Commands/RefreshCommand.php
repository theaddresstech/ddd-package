<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class RefreshCommand extends Command
{
    protected $signature = 'module:migrate-refresh {module?} {--force} {--database=} {--subpath=}';

    protected $description = 'Roll module migrations back and run them again';

    public function handle(ModuleCli $cli): int
    {
        return $cli->refresh($this);
    }
}

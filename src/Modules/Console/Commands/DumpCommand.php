<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class DumpCommand extends Command
{
    protected $signature = 'module:dump {module?}';

    protected $description = 'Run Composer dump-autoload';

    public function handle(ModuleCli $cli): int
    {
        return $cli->dump($this);
    }
}

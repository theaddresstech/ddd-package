<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeJobCommand extends Command
{
    protected $signature = 'module:make-job {name} {module?} {--sync}';

    protected $description = 'Create a job';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

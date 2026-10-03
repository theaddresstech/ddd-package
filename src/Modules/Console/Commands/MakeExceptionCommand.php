<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeExceptionCommand extends Command
{
    protected $signature = 'module:make-exception {name} {module?}';

    protected $description = 'Create an exception';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

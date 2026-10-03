<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeCommand extends Command
{
    protected $signature = 'module:make {name*} {--plain} {--api} {--disabled} {--force}';

    protected $description = 'Create one or more modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->make($this);
    }
}

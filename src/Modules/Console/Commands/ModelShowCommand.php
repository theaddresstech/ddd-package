<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class ModelShowCommand extends Command
{
    protected $signature = 'module:model-show {model} {module?}';

    protected $description = 'Show a module model attributes and relations';

    public function handle(ModuleCli $cli): int
    {
        return $cli->modelShow($this);
    }
}

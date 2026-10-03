<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class MakeTraitCommand extends Command
{
    protected $signature = 'module:make-trait {name} {module?}';

    protected $description = 'Create a trait';

    public function handle(ModuleCli $cli): int
    {
        return $cli->artifact($this);
    }
}

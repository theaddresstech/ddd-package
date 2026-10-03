<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class UnuseCommand extends Command
{
    protected $signature = 'module:unuse';

    protected $description = 'Forget the remembered module';

    public function handle(ModuleCli $cli): int
    {
        return $cli->unuse($this);
    }
}

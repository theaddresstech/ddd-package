<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class UpdatePhpunitCommand extends Command
{
    protected $signature = 'module:update-phpunit-coverage';

    protected $description = 'Add enabled modules to phpunit.xml';

    public function handle(ModuleCli $cli): int
    {
        return $cli->updatePhpunit($this);
    }
}

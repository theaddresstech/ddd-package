<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class InstallCommand extends Command
{
    protected $signature = 'module:install {package}';

    protected $description = 'Install a module from a Composer package name';

    public function handle(ModuleCli $cli): int
    {
        return $cli->install($this);
    }
}

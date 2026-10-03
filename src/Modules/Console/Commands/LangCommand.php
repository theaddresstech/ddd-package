<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class LangCommand extends Command
{
    protected $signature = 'module:lang {module?}';

    protected $description = 'Report translation keys missing from a module';

    public function handle(ModuleCli $cli): int
    {
        return $cli->lang($this);
    }
}

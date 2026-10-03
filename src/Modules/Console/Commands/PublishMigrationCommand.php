<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PublishMigrationCommand extends Command
{
    protected $signature = 'module:publish-migration {module?}';

    protected $description = 'Copy module migrations into the application';

    public function handle(ModuleCli $cli): int
    {
        return $cli->publishMigration($this);
    }
}

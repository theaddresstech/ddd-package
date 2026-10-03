<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PublishInertiaCommand extends Command
{
    protected $signature = 'module:publish-inertia {--frontend=vue}';

    protected $description = 'Publish an Inertia page resolver for every module';

    public function handle(ModuleCli $cli): int
    {
        return $cli->publishInertia($this);
    }
}

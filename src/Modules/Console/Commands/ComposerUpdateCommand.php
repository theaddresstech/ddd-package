<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class ComposerUpdateCommand extends Command
{
    protected $signature = 'module:composer-update {module?}';

    protected $description = 'Rewrite a module composer.json autoload';

    public function handle(ModuleCli $cli): int
    {
        return $cli->composerUpdate($this);
    }
}

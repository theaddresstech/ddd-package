<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class PublishTranslationCommand extends Command
{
    protected $signature = 'module:publish-translation {module?}';

    protected $description = 'Copy module translations so the app can override them';

    public function handle(ModuleCli $cli): int
    {
        return $cli->publishTranslation($this);
    }
}

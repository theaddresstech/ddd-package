<?php

namespace theaddresstechnology\DDD\Modules\Console\Commands;

use Illuminate\Console\Command;
use theaddresstechnology\DDD\Modules\Console\ModuleCli;

class UpdateCommand extends Command
{
    protected $signature = 'module:update {package?}';

    protected $description = 'Update Composer dependencies for modules';

    public function handle(ModuleCli $cli): int
    {
        return $cli->update($this);
    }
}

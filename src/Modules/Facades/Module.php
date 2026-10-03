<?php

namespace theaddresstechnology\DDD\Modules\Facades;

use Illuminate\Support\Facades\Facade;
use theaddresstechnology\DDD\Modules\Repository;

class Module extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Repository::class;
    }
}

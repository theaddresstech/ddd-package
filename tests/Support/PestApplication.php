<?php

namespace Tests\Support;

abstract class PestApplication extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\theaddresstechnology\DDD\DomainDriverDesignServiceProvider::class];
    }
}

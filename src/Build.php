<?php

namespace theaddresstechnology\DDD;

use ReflectionClass;
use theaddresstechnology\DDD\Helper\Path;
use theaddresstechnology\DDD\Helper\Stub;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use theaddresstechnology\DDD\Helper\ArrayFormatter;
use Illuminate\Support\Facades\Artisan;
use theaddresstechnology\DDD\Helper\Make\Types\Domain;
use theaddresstechnology\DDD\Helper\Make\Types\FirstDomain;


/**
 * Generate Main DDD Direcotry-Sturcutre
 */
class Build extends Command
{
    private $app_path;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ddd:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build domains ';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->error('ddd:build is not available.');

        return 1;
    }

}

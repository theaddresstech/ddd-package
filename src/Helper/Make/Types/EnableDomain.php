<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Macroable;
use theaddresstechnology\DDD\Helper\FileCreator;
use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\Naming;
use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use MohamedReda\DDD\Helper\Make\Types\Rule;

class EnableDomain extends Maker
{
    /**
     * Holds the domain's name
     *
     * @var string
     */
    private $name;
    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [
        'domain',
    ];

    /**
     * Return options that should be treated as choices
     *
     * @return Array
     */
    public $allowChoices = [
        'domain',
    ];

    /**
     * Check if the current options is True/False question
     *
     * @return Array
     */
    public $booleanOptions = [];

    /**
     * Fill all placeholders in the stub file
     *
     * @return Boll
     */
    public function service(Array $values):Bool{
        $this->name=$values['domain'];

        return $this->modifyConfig();

    }
    public function modifyConfig(){

        // Add Service Provider to bootstrap/providers
        $service_provider = NamespaceCreator::Segments("Src","Domain",$this->name,"Providers","DomainServiceProvider");
        $app = File::get(base_path('bootstrap'.DIRECTORY_SEPARATOR.'providers.php'));
       if(Str::of($app)->contains([$service_provider],[false]) ==false) {
           $content = Str::of($app)->replace("###DOMAINS SERVICE PROVIDERS###", $service_provider . "::class,\n\t\t###DOMAINS SERVICE PROVIDERS###");

           $this->save(base_path().DIRECTORY_SEPARATOR."bootstrap", 'providers', 'php', $content);

           $migration_path="src".DIRECTORY_SEPARATOR."Domain".DIRECTORY_SEPARATOR.$this->name.DIRECTORY_SEPARATOR."Database".DIRECTORY_SEPARATOR."Migrations";

           \Illuminate\Support\Facades\Artisan::call("migrate",["--path"=>$migration_path]);

           return true;
       }
        error_log("This Domain Is Already Enabled");

        return false;
    }
}

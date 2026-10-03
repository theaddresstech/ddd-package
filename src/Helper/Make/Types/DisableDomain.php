<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\SafePath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DisableDomain extends Maker
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
     * Check if the current options is requesd based on other option
     *
     * @return Array
     */
    public $requiredUnless = [];

    /**
     * Fill all placeholders in the stub file
     *
     * @return Boll
     */
    public function service(Array $values):Bool{
        $this->name = SafePath::className($values['domain']);

        return $this->modifyConfig();

    }
    public function modifyConfig(){

        $service_provider = NamespaceCreator::Segments("Src","Domain",$this->name,"Providers","DomainServiceProvider");
        $providers = base_path('bootstrap'.DIRECTORY_SEPARATOR.'providers.php');

        if (!File::isFile($providers)) {
            $this->command?->error('bootstrap/providers.php was not found.');

            return false;
        }

        $app = File::get($providers);

        if(Str::of($app)->contains([$service_provider],[false]) ==true) {
            $content = Str::of($app)->replace($service_provider."::class,","");

            $this->save(base_path().DIRECTORY_SEPARATOR."bootstrap", 'providers', 'php', $content);
            $this->command?->info('Domain provider removed. Tables were left in place.');

            return true;
        }
        error_log("This Domain Is Already Disabled");

        return false;
    }
}

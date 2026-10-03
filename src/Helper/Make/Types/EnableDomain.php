<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\SafePath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        'force',
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
    public $booleanOptions = [
        'force',
    ];

    /**
     * Fill all placeholders in the stub file
     *
     * @return Boll
     */
    public function service(Array $values):Bool{
        $this->name = SafePath::className($values['domain']);
        $repository = \theaddresstechnology\DDD\Modules\Repository::class;
        if (app()->bound($repository) && app($repository)->has($this->name)) {
            app($repository)->enable($this->name);

            return true;
        }

        return $this->modifyConfig($values);

    }
    public function modifyConfig(array $values = []): Bool{

        $service_provider = NamespaceCreator::Segments("Src","Domain",$this->name,"Providers","DomainServiceProvider");
        $providers = base_path('bootstrap'.DIRECTORY_SEPARATOR.'providers.php');

        if (!File::isFile($providers)) {
            $this->command?->error('bootstrap/providers.php was not found.');

            return false;
        }

        $app = File::get($providers);
       if(Str::of($app)->contains([$service_provider],[false]) ==false) {
           $content = Str::of($app)->replace("###DOMAINS SERVICE PROVIDERS###", $service_provider . "::class,\n\t\t###DOMAINS SERVICE PROVIDERS###");

           $this->save(base_path().DIRECTORY_SEPARATOR."bootstrap", 'providers', 'php', $content);

           $migration_path = base_path('src'.DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.$this->name.DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Migrations');

           if (File::isDirectory($migration_path)) {
               $parameters = ['--path' => $migration_path];
               if (!empty($values['force'])) {
                   $parameters['--force'] = true;
               }
               Artisan::call('migrate', $parameters);
           }

           return true;
       }
        error_log("This Domain Is Already Enabled");

        return false;
    }
}

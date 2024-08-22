<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $this->name=$values['domain'];

        return $this->modifyConfig();

    }
    public function modifyConfig(){

        // Remove Service Provider from boostrap/providers
        $service_provider = NamespaceCreator::Segments("Src","Domain",$this->name,"Providers","DomainServiceProvider");
        $app = File::get(base_path('bootstrap'.DIRECTORY_SEPARATOR.'providers.php'));
        //
        if(Str::of($app)->contains([$service_provider],[false]) ==true) {
            $content = Str::of($app)->replace($service_provider."::class,","");

            $this->save(base_path().DIRECTORY_SEPARATOR."bootstrap", 'providers', 'php', $content);

            $migration_path="src".DIRECTORY_SEPARATOR."Domain".DIRECTORY_SEPARATOR.$this->name.DIRECTORY_SEPARATOR."Database".DIRECTORY_SEPARATOR."Migrations";

            $entities=Path::files("src".DIRECTORY_SEPARATOR."Domain".DIRECTORY_SEPARATOR.$this->name.DIRECTORY_SEPARATOR."Entities");

            foreach ($entities as $entity){
                $class = NamespaceCreator::entity($this->name,$entity);
                $table = with(new $class)->getTable();
                Schema::dropIfExists($table);
                //dd($table);
            }
            $migration_files=Path::files($migration_path);
            foreach ($migration_files as$migration_file){
                DB::table('migrations')->where('migration',$migration_file)->delete();
            }
            // \Illuminate\Support\Facades\Artisan::call("migrate:rollback",["--path"=>$migration_path,'--force'=>1]);

            return true;
        }
        error_log("This Domain Is Already Disabled");

        return false;
    }
}

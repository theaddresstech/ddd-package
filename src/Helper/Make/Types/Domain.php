<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\ArrayFormatter;
use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\Naming;
use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Domain extends Maker
{
    /**
     * Holds the domain's name
     *
     * @var string
     */
    private $name;

    /**
     * Holds the domain's name
     *
     * @var string
     */
    private $alias;

    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [
        'name',
    ];

    /**
     * Return options that should be treated as choices
     *
     * @return Array
     */
    public $allowChoices = [];

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
     * Return full path of the Domain
     *
     * @return void
     */
    private function path():string{
        return base_path("src".DIRECTORY_SEPARATOR.config('ddd.path')).DIRECTORY_SEPARATOR.$this->name;
    }


    /**
     * Process command request
     *User
     * @return boolean
     */
    public function service(Array $values = []):bool{

        $this->name = Naming::class($values['name']);
        $this->alias = Naming::DomainAlias($values['name']);

        $this->createDirectories();

        $this->createProviders();

        $this->createRoutes();

        $this->modifyConfig();

        $this->resourceFolder();

        $this->createTests();

        return true;
    }


    /**
     * Create Directories for the domain
     *
     * @return void
     */
    private function createDirectories(){

        $directories = ArrayFormatter::directories(config('ddd.structure.domain'));

        if(File::isDirectory($this->path())){
            $this->command->error("Domain Exists!");
            return false;
        }else{
            File::makeDirectory($this->path());

            foreach($directories as $directory){

                File::makeDirectory($this->path().DIRECTORY_SEPARATOR.$directory,0755,true,true);

            }
        }
        return true;
    }

    /**
     * Create Provders
     *
     * @return void
     */
    private function createProviders(){

        $DSPStub = File::get(Path::stub('Domain','Providers','DomainServiceProvider.stub'));
        $ESVStub = File::get(Path::stub('Domain','Providers','EventServiceProvider.stub'));
        $HSPStub = File::get(Path::stub('Domain','Providers','HelperServiceProvider.stub'));
        $PSPStub = File::get(Path::stub('Domain','Providers','PolicyServiceProvider.stub'));
        $ReSPStub = File::get(Path::stub('Domain','Providers','RepositoryServiceProvider.stub'));
        $RoSPStub = File::get(Path::stub('Domain','Providers','RouteServiceProvider.stub'));

        $serviceServicers = [
            [
                "php"=>'DomainServiceProvider',
                "stub"=>$DSPStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                    '{{ALIAS}}'=>$this->alias,
                ]
            ],
            [
                "php"=>'EventServiceProvider',
                "stub"=>$ESVStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                ]
            ],
            [
                "php"=>'HelperServiceProvider',
                "stub"=>$HSPStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                ]
            ],
            [
                "php"=>'PolicyServiceProvider',
                "stub"=>$PSPStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                ]
            ],
            [
                "php"=>'RepositoryServiceProvider',
                "stub"=>$ReSPStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                ]
            ],
            [
                "php"=>'RouteServiceProvider',
                "stub"=>$RoSPStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                ]
            ],
        ];

        foreach($serviceServicers as $sp){

            $destination = path::build($this->path(),'Providers');

            $content = Str::of($sp['stub'])->replace(array_keys($sp['params']),array_values($sp['params']));

            $this->save($destination,$sp['php'],'php',$content);

        }
    }


    /**
     * Create routes files for web&api
     *
     * @return void
     */
    public function createRoutes(){
        // API
        $files = ['auth','guest','public'];
        foreach($files as $file){
            $api_content = File::get(path::build(Path::stub(),'Domain','Routes','api',$file.'.stub'));
            $this->save(path::build($this->path(),'Routes','api'),$file,'php',$api_content);
        }

        foreach($files as $file){
            $web_content = File::get(path::build(Path::stub(),'Domain','Routes','web',$file.'.stub'));
            $this->save(path::build($this->path(),'Routes','web'),$file,'php',$web_content);
        }
    }

    public function modifyConfig(){

        // Add Service Providers to bootstrap/providers.php
        $service_provider = NamespaceCreator::Segments("Src","Domain",$this->name,"Providers","DomainServiceProvider");
        $app = File::get(base_path('bootstrap').DIRECTORY_SEPARATOR.'providers.php');
        $content = Str::of($app)->replace("###DOMAINS SERVICE PROVIDERS###",$service_provider."::class,\n\t\t###DOMAINS SERVICE PROVIDERS###");
        $this->save(base_path('bootstrap'),'providers','php',$content);
    }

    public function resourceFolder(){
        File::makeDirectory($this->path().DIRECTORY_SEPARATOR."Resources".DIRECTORY_SEPARATOR."Views",0777, true, true);
    }

    public function createTests(){

        $FeatureTestStub = File::get(Path::stub('Domain', 'Tests', 'Feature.stub'));
        $UnitTestStub    = File::get(Path::stub('Domain', 'Tests', 'Unit.stub'));

        $tests = [
            [
                "php"=>'FeatureTest',
                "stub"=>$FeatureTestStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                    '{{ALIAS}}'=>$this->alias,
                ]
            ],
            [
                "php"=>'UnitTest',
                "stub"=>$UnitTestStub,
                'params'=>[
                    '{{NAME}}'=>$this->name,
                    '{{ALIAS}}'=>$this->alias,
                ]
            ],
        ];

        foreach($tests as $test) {

            $destination = path::build($this->path(),'Tests');

            $content = Str::of($test['stub'])->replace(array_keys($test['params']),array_values($test['params']));

            $this->save($destination,$test['php'],'php',$content);

        }
    }

}

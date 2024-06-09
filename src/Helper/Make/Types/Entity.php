<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\Naming;
use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Str;

class Entity extends Maker
{
    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [
        'name',
        'domain'
    ];

    /**
     * Return options that should be treated as choices
     *
     * @return Array
     */
    public $allowChoices = [
        'domain'
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
     * @return bool
     */
    public function service(Array $values = []): bool{

        $this->createEntity($values);
        $this->createRelations($values);
        $this->createAttributes($values);
        $this->createLocalScopes($values);

        return true;
    }

    private function createEntity($values){
        $name   = Naming::class($values['name']);
        $table  = Naming::TableName($values['name']);

        $entity_placholder = [
            "{{NAME}}"  => $name,
            "{{DOMAIN}}"=> $values['domain'],
            "{{TABLE}}" => $table,
        ];

        $entity_destination = Path::toDomain($values['domain'],'Entities');
        $entity_content = Str::of($this->getStub('entity'))->replace(array_keys($entity_placholder),array_values($entity_placholder));
        $this->save($entity_destination,$name,'php',$entity_content);

    }

    private function createRelations($values){

        $name         = Naming::class($values['name']);
        $file       = Naming::class($values['name'],'relations');

        $relations_placholder = [
            "{{NAME}}"  => $name,
            "{{DOMAIN}}"=> $values['domain'],
        ];

        $relations_destination = Path::toDomain($values['domain'],'Entities','Traits','Relations');

        $relation_content = Str::of($this->getStub('relation'))->replace(array_keys($relations_placholder),array_values($relations_placholder));

        $this->save($relations_destination,$file,'php',$relation_content);

    }

    private function createAttributes($values){
        $name       = Naming::class($values['name']);
        $file       = Naming::class($values['name'],'attributes');

        $attributes_placholder = [
            "{{NAME}}"  => $name,
            "{{DOMAIN}}"=> $values['domain'],
        ];
        $attributes_destination = Path::toDomain($values['domain'],'Entities','Traits','CustomAttributes');
        $attributes_content = Str::of($this->getStub('customer-attributes'))->replace(array_keys($attributes_placholder),array_values($attributes_placholder));
        $this->save($attributes_destination,$file,'php',$attributes_content);
    }

    private function createLocalScopes($values){

        $name       = Naming::class($values['name']);
        $file       = Naming::class($values['name'],'LocalScopes');

        $file=Str::replace('scopes','Scopes',$file);

        $local_scopes_placholder = [
            "{{NAME}}"  => $name,
            "{{DOMAIN}}"=> $values['domain'],
        ];

        $local_scopes_destination = Path::toDomain($values['domain'],'Entities','Traits','LocalScopes');

        $local_scopes_content = Str::of($this->getStub('local-scopes'))->replace(array_keys($local_scopes_placholder),array_values($local_scopes_placholder));

        $this->save($local_scopes_destination,$file,'php',$local_scopes_content);
    }

}

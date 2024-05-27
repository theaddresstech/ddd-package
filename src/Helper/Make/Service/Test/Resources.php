<?php

namespace theaddresstechnology\DDD\Helper\Make\Service\Test;

use Illuminate\Support\Str;
use theaddresstechnology\DDD\Helper\Path;
use theaddresstechnology\DDD\Helper\Naming;
use Src\Domain\User\Entities\User;
use theaddresstechnology\DDD\Helper\Make\Maker;
use Illuminate\Support\Facades\File;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\Make\Service\Test\Test;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Domain\User\Http\Resources\User\UserResource;
use ReflectionClass;

class Resources extends Test
{
    private $domain;
    private $entitiesDirPath;
    private $entities;
    private $entityInstance;
    private $entityShortName;
    private $TestCommand;

    public function __construct(Maker $TestCommand, string $domain)
    {
        $this->domain = $domain;
        $this->resourcesDirPath = ['Src', 'Domain', $domain, 'Http', 'Resources'];
        $this->entitiesDirPath = ['Src', 'Domain', $domain, 'Entities'];
        $this->entities = Path::directories(...$this->resourcesDirPath);
        $this->TestCommand = $TestCommand;
    }

    public function generate()
    {
        foreach ($this->entities as $entity) {
            $resource = $entity . 'Resource';
            $this->entityInstance = $this->instantiateJustCreated($this->entitiesDirPath, $entity);
            $entityNameSpace = join('\\', array_merge($this->entitiesDirPath, [$entity]));
//            $this->entityRecord = factory($entityNameSpace)->make([
//                'id' => 1,
//            ]);
            // TODO: fix factory helper issue in the original package
            $class = new \StdClass();
            $class->id = 1;
            $class->name = 'test';

            $resourceInstance = $this->instantiateJustCreated(array_merge($this->resourcesDirPath, [$entity]), $resource, $class);

            $this->createBasicTestCases($resourceInstance);

            $placeholders = [
                '{{RESOURCE}}' => $resource,
                '{{ENTITY}}' => $entity,
                '{{DOMAIN}}' => $this->domain,
                '{{TESTCASES}}' => $this->testCases['basic'],
                // '{{JWTMETHODS}}' => $this->createJWTMethods(),
                '{{SETUP}}' => $this->createSetupMethod($entity)
            ];

            $dir = Path::toDomain($this->domain, 'Tests', 'Unit', 'Entities');

            $content = Str::of($this->TestCommand->getStub('resource-test'))
                ->replace(array_keys($placeholders), array_values($placeholders));

            $classFullName = $resource . 'Test';

            $this->TestCommand->save($dir, $classFullName, 'php', $content);
        }
    }

    protected function createNormalresource(JsonResource $resourceInstance)
    {
        $resource = explode("\\", get_class($resourceInstance));
        $entity = explode("\\", get_class($this->entityInstance));

        $placeholders = [
            '{{RESOURCE}}' => end($resource),
            '{{RSOURCE_CC}}' => Str::camel(end($resource)),
            '{{CONTENT}}' => join('","', array_keys($resourceInstance->resolve())),
            '{{ENTITY_LC}}' => Str::lower(end($entity))
        ];

        return Str::of($this->TestCommand->getStub('resource-normal-test'))
            ->replace(array_keys($placeholders), array_values($placeholders));
    }

    public function createRelationships(JsonResource $resourceInstance)
    {
        dd(new ReflectionClass($resourceInstance));
    }

    public function createSetupMethod(string $entity)
    {
        $placeholders = [
            '{{ENTITY_LC}}' => Str::lower($entity),
            '{{ENTITY}}' => Str::ucfirst($entity),
            '{{RESOURCE_CC}}' => Str::lower($entity) . 'Resource',
            '{{RESOURCE}}' => $entity . 'Resource',
        ];

        return Str::of($this->TestCommand->getStub('entity-setup-method'))->replace(array_keys($placeholders), array_values($placeholders));
    }
}

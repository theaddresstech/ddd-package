<?php

namespace Tests;

use Illuminate\Http\Request;
use Src\Domain\User\Entities\User;
use Src\Infrastructure\l5\Criteria\RequestCriteria;
use Tests\Support\GeneratedApplication;

class GeneratedQuerySecurityTest extends GeneratedApplication
{
    public function test_client_search_operators_cannot_add_unapproved_columns(): void
    {
        $criteria = new RequestCriteria(Request::create('/'));
        $parse = new \ReflectionMethod($criteria, 'parserFieldsSearch');
        foreach ([['name'], ['name' => '=']] as $fields) {
            try {
                $parse->invoke($criteria, $fields, ['password:like'], ['password']);
                $this->fail('Client added a private search column.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(['name' => 'like'], $parse->invoke($criteria, ['name'], ['name:like'], ['name']));
        $this->assertSame(['name' => 'like'], $parse->invoke($criteria, ['name' => '='], ['name:like'], ['name']));
    }

    public function test_query_projection_sorting_and_relations_require_repository_allowlists(): void
    {
        $repository = $this->repository();
        foreach ([['filter' => 'password'], ['orderBy' => 'password'], ['with' => 'tokens'], ['withCount' => 'tokens'], ['search' => ['invalid']]] as $query) {
            try {
                (new RequestCriteria(Request::create('/', 'GET', $query)))->apply(User::query(), $repository);
                $this->fail('Unexpected query parameter was accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $builder = (new RequestCriteria(Request::create('/', 'GET', ['filter' => 'name', 'orderBy' => 'name', 'with' => 'tokens'])))
            ->apply(User::query(), $this->repository(['name'], ['name'], ['tokens']));
        $this->assertSame(['name'], $builder->getQuery()->columns);
        $this->assertSame([['column' => 'name', 'direction' => 'asc']], $builder->getQuery()->orders);
        $this->assertArrayHasKey('tokens', $builder->getEagerLoads());
    }

    public function test_allowed_search_keeps_values_bound_as_data(): void
    {
        $value = "' OR 1=1 --";
        $builder = (new RequestCriteria(Request::create('/', 'GET', ['search' => $value, 'searchJoin' => 'and'])))
            ->apply(User::query(), $this->repository());
        $this->assertSame([$value], $builder->getBindings());
        $this->assertStringNotContainsString($value, $builder->toSql());
    }

    private function repository(array $fields = [], array $sorts = [], array $includes = []): \Src\Infrastructure\AbstractRepositories\EloquentRepository
    {
        $repository = new class($this->app) extends \Src\Infrastructure\AbstractRepositories\EloquentRepository {
            protected $fieldSearchable = ['name'];

            public function model() { return User::class; }

            public function allow(array $fields, array $sorts, array $includes): void
            {
                $this->allowedFields = $fields;
                $this->allowedSorts = $sorts;
                $this->allowedIncludes = $includes;
            }
        };
        $repository->allow($fields, $sorts, $includes);

        return $repository;
    }
}

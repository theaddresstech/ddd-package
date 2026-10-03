<?php

namespace Tests\Support;

use Orchestra\Testbench\TestCase;

abstract class GeneratedApplication extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static $loaded = false;
        if (!$loaded) {
            $tokens = [
                '{{DOMAIN}}' => 'User', '{{NAME}}' => 'User', '{{ENTITY}}' => 'User',
                '{{ENTITY_LC}}' => 'user', '{{ENTITY_PL}}' => 'users',
                '{{NAME_REQUEST}}' => 'User', '{{NAME_REQUEST_STORE}}' => 'UserStoreFormRequest',
                '{{NAME_REQUEST_UPDATE}}' => 'UserUpdateFormRequest', '{{NAME_REPO}}' => 'UserRepository',
                '{{NAME_REPO_VAR}}' => 'users', '{{VIEW_RESOURCE}}' => 'user',
                '{{RESOURCE_ROUTE_NAME}}' => 'users', '{{DOMAIN_ALIAS}}' => 'users',
                '{{API_RESOURCE_NAME}}' => 'User',
            ];
            foreach ([
                'Infrastructure/Http/AbstractControllers/BaseController.stub',
                'Infrastructure/Http/AbstractRequests/BaseRequest.stub',
                'Domain/Entities/Traits/Relations/relations.stub',
                'Domain/Entities/Traits/CustomAttributes/attributes.stub',
                'User/entity.stub',
                'Domain/Http/Requests/Entity/store.stub',
                'Domain/Http/Requests/Entity/update.stub',
                'Infrastructure/l5/Contracts/RepositoryInterface.stub',
                'Infrastructure/l5/Contracts/CriteriaInterface.stub',
                'Infrastructure/l5/Criteria/RequestCriteria.stub',
                'Infrastructure/l5/Contracts/RepositoryCriteriaInterface.stub',
                'Infrastructure/l5/Exceptions/RepositoryException.stub',
                'Infrastructure/l5/Eloquent/BaseRepository.stub',
                'Infrastructure/AbstractRepositories/EloquentRepository.stub',
                'Domain/Repositories/Contracts/contract.stub',
                'Domain/Http/Controllers/controller.stub',
                'Infrastructure/Http/AbstractResources/BaseResource.stub',
                'User/Auth/LoginController.php',
                'Common/Helpers/Main.stub',
                'Common/Helpers/UploadHelper.stub',
                'Common/Http/Middleware/Admin.stub',
                'Dashboard/Http/Controllers/ConfigureDomainController.php',
                'Dashboard/Http/Requests/ConfigureDomain/ConfigureDomainFormRequest.php',
            ] as $stub) {
                $this->loadStub($stub, $tokens);
            }
            $this->loadStub('Domain/Http/Resources/resource.stub', array_replace($tokens, ['{{NAME}}' => 'UserResource']));
            $this->loadStub('Domain/Policies/policy.stub', array_replace($tokens, ['{{NAME}}' => 'UserPolicy']));
            $loaded = true;
        }
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:']);
        $app['config']->set('auth.providers.users.model', \Src\Domain\User\Entities\User::class);
        $app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('hashing.bcrypt.rounds', 4);
    }

    protected function loadStub(string $stub, array $tokens = []): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ddd-test-stub-');
        try {
            file_put_contents($file, strtr(file_get_contents(__DIR__.'/../../stub/'.$stub), $tokens));
            require $file;
        } finally {
            unlink($file);
        }
    }

    protected function usersTable(): void
    {
        \Illuminate\Support\Facades\Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }
}

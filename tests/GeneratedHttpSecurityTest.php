<?php

namespace Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Src\Domain\User\Entities\User;
use Tests\Support\GeneratedApplication;

class GeneratedHttpSecurityTest extends GeneratedApplication
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->usersTable();
        Gate::policy(User::class, \Src\Domain\User\Policies\UserPolicy::class);
    }

    public function test_generated_user_hashes_passwords_and_does_not_rehash_existing_hashes(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.test', 'password' => 'a-strong-password']);
        $this->assertNotSame('a-strong-password', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $hash = $user->password;
        $user->password = $hash;
        $this->assertSame($hash, $user->password);
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_all_generated_crud_actions_deny_before_touching_the_repository(): void
    {
        $this->actingAs(new User(['name' => 'Unprivileged']));
        $repository = $this->createMock(\Src\Domain\User\Repositories\Contracts\UserRepository::class);
        foreach (['create', 'update', 'find', 'delete', 'paginate'] as $method) {
            $repository->expects($this->never())->method($method);
        }
        $controller = new \Src\Domain\User\Http\Controllers\UserController($repository);
        $model = new User();
        $model->id = 42;
        $calls = [
            ['index', [Request::create('/users')]],
            ['store', [new \Src\Domain\User\Http\Requests\User\UserStoreFormRequest()]],
            ['show', [$model]],
            ['update', [new \Src\Domain\User\Http\Requests\User\UserUpdateFormRequest(), $model]],
            ['destroy', [$model]],
        ];
        foreach ($calls as [$method, $arguments]) {
            try {
                $controller->$method(...$arguments);
                $this->fail('Denied CRUD action ran: '.$method);
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_record_policy_prevents_deleting_another_users_record_but_allows_own(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret-password']);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret-password']);
        $this->actingAs($owner);
        Gate::policy(User::class, OwnUserPolicy::class);
        $controller = new \Src\Domain\User\Http\Controllers\UserController($this->createMock(\Src\Domain\User\Repositories\Contracts\UserRepository::class));
        try {
            $controller->destroy($other);
            $this->fail('Another user was deleted.');
        } catch (AuthorizationException) {
            $this->assertTrue(User::whereKey($other->id)->exists());
        }
        $this->app->instance('request', Request::create('/users', 'DELETE', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
        $controller->destroy($owner);
        $this->assertFalse(User::whereKey($owner->id)->exists());
    }

    public function test_domain_routes_require_api_authentication_and_explicit_permission(): void
    {
        require __DIR__.'/../stub/Dashboard/Routes/api/auth.php';
        Artisan::swap(\Mockery::mock(\Illuminate\Contracts\Console\Kernel::class));
        Artisan::shouldReceive('call')->never();
        $this->getJson('/get_domain')->assertUnauthorized();
        $this->postJson('/disable_domain', ['name' => 'User'])->assertUnauthorized();
        $this->actingAs(new User(), 'api');
        $this->getJson('/get_domain')->assertForbidden();
        $this->postJson('/enable_domain', ['name' => 'User'])->assertForbidden();
        $this->postJson('/disable_domain', ['name' => 'User'])->assertForbidden();
    }

    public function test_domain_form_request_requires_an_explicit_gate_grant(): void
    {
        $request = new \Src\Domain\Dashboard\Http\Requests\ConfigureDomain\ConfigureDomainFormRequest();
        $user = new User();
        $request->setUserResolver(fn () => $user);
        $this->assertFalse($request->authorize());
        Gate::define('manage-domains', fn (User $actor) => $actor === $user);
        $this->assertTrue($request->authorize());
    }

    public function test_permission_helper_does_not_reuse_another_users_permissions(): void
    {
        Gate::define('export', fn (User $user) => $user->name === 'Admin');
        $this->actingAs(new User(['name' => 'Admin']));
        $this->assertTrue(userCan('export'));
        $this->actingAs(new User(['name' => 'Member']));
        $this->assertFalse(userCan('export'));
        $this->app['auth']->forgetGuards();
        $this->assertFalse(userCan('export'));
    }

    public function test_login_rejects_invalid_shapes_and_rate_limits_bad_credentials(): void
    {
        Route::post('/login', \Src\Domain\User\Http\Controllers\Auth\LoginController::class);
        $this->postJson('/login', ['email' => ['bad'], 'password' => ['bad']])->assertUnprocessable();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/login', ['email' => 'UNKNOWN@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_stateless_login_uses_web_credentials_without_creating_a_session(): void
    {
        // Verify the controller chooses the credential guard and stateless auth method.
        // OAuth signing is supplied by the host application.
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->name = 'User';
        $user->shouldReceive('createToken')->once()->andReturn(new \Laravel\Passport\PersonalAccessTokenResult(['access_token' => 'test-token']));
        $guard = \Mockery::mock(\Illuminate\Contracts\Auth\StatefulGuard::class);
        $guard->shouldReceive('once')->once()->with(['email' => 'user@example.test', 'password' => 'secret-password'])->andReturn(true);
        $guard->shouldReceive('user')->andReturn($user);
        $guard->shouldNotReceive('attempt');
        $this->app['auth']->setDefaultDriver('api');
        $this->app['auth']->extend('probe', fn () => $guard);
        config(['auth.guards.web.driver' => 'probe']);
        $request = Request::create('/login', 'POST', ['email' => 'user@example.test', 'password' => 'secret-password'], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $request);
        $resource = (new \Src\Domain\User\Http\Controllers\Auth\LoginController())($request);
        $this->assertFalse($request->hasSession());
        $this->assertSame('test-token', $resource->additional['meta']['token']);
    }

    public function test_session_login_rotates_the_session_identifier(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('createToken')->once()->andReturn(new \Laravel\Passport\PersonalAccessTokenResult(['access_token' => 'test-token']));
        $guard = \Mockery::mock(\Illuminate\Contracts\Auth\StatefulGuard::class);
        $guard->shouldReceive('attempt')->once()->andReturn(true);
        $guard->shouldReceive('user')->andReturn($user);
        $guard->shouldNotReceive('once');
        $this->app['auth']->extend('probe', fn () => $guard);
        config(['auth.guards.web.driver' => 'probe']);
        $request = Request::create('/login', 'POST', ['email' => 'user@example.test', 'password' => 'secret-password'], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $before = $session->getId();
        $this->app->instance('request', $request);
        (new \Src\Domain\User\Http\Controllers\Auth\LoginController())($request);
        $this->assertNotSame($before, $session->getId());
    }
}

class OwnUserPolicy
{
    public function delete(User $user, User $record): bool
    {
        return $user->id === $record->id;
    }
}

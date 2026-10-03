<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Src\Domain\User\Entities\User;
use Tests\Support\GeneratedApplication;

class GeneratedV2DependenciesTest extends GeneratedApplication
{
    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Passport\PassportServiceProvider::class,
            \Spatie\QueryBuilder\QueryBuilderServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->usersTable();
        if (!trait_exists(\Src\Infrastructure\Traits\SpatieQueryBuilder::class, false)) {
            $this->loadStub('Infrastructure/Traits/SpatieQueryBuilder.stub');
        }
    }

    public function test_generated_repository_filters_projects_and_sorts_with_query_builder_7(): void
    {
        User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'password']);
        User::create(['name' => 'Grace', 'email' => 'grace@example.test', 'password' => 'password']);
        $this->app->instance('request', Request::create('/', 'GET', ['filter' => ['name' => 'Ada'], 'sort' => '-name', 'fields' => ['users' => 'id,name']]));
        $repository = new class($this->app) extends \Src\Infrastructure\AbstractRepositories\EloquentRepository {
            protected $allowedFields = ['id', 'name'];
            protected $allowedFiltersExact = ['name'];
            protected $allowedSorts = ['name'];
            public function model() { return User::class; }
        };
        $result = $repository->spatie()->all();
        $this->assertCount(1, $result);
        $this->assertSame('Ada', $result->first()->name);
        $this->assertSame(['id', 'name'], array_keys($result->first()->getAttributes()));
    }

    public function test_generated_query_trait_supports_all_lookup_methods_and_rejects_private_filters(): void
    {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'password']);
        $repository = new class {
            use \Src\Infrastructure\Traits\SpatieQueryBuilder;
            public $entity;
            protected $relations = [];
            protected $allowedFields = ['id', 'name'];
            protected $allowedFilters = ['name'];
            protected $allowedIncludes = [];
            protected $allowedSorts = ['name'];
            protected $limit = 10;
            public function __construct() { $this->entity = new User(); }
        };
        $this->assertCount(1, $repository->iall());
        $this->assertSame(1, $repository->ipaginate()->total());
        $this->assertSame($user->id, $repository->ifind($user->id)->id);
        $this->assertCount(1, $repository->ifindMany([$user->id]));
        $this->assertCount(1, $repository->ifindWhere(['name' => 'Ada']));
        $this->assertSame($user->id, $repository->ifindWhereFirst(['name' => 'Ada'])->id);
        $this->app->instance('request', Request::create('/', 'GET', ['filter' => ['password' => 'secret']]));
        $this->app->forgetInstance(\Spatie\QueryBuilder\QueryBuilderRequest::class);
        $this->expectException(\Spatie\QueryBuilder\Exceptions\InvalidFilterQuery::class);
        $repository->iall();
    }

    public function test_generated_login_issues_a_real_passport_token_that_authenticates_and_revokes(): void
    {
        foreach (glob(__DIR__.'/../vendor/laravel/passport/database/migrations/*.php') as $file) {
            (require $file)->up();
        }
        $keys = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keys, $private);
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($keys)['key'],
            'auth.guards.api' => ['driver' => 'passport', 'provider' => 'users'],
        ]);
        app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('DDD v2 test', 'users');
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'a-strong-password']);
        $this->assertInstanceOf(\Laravel\Passport\Contracts\OAuthenticatable::class, $user);
        Route::post('/v2-login', \Src\Domain\User\Http\Controllers\Auth\LoginController::class);
        Route::get('/v2-protected', fn () => ['id' => auth('api')->id()])->middleware('auth:api');
        $login = $this->postJson('/v2-login', ['email' => 'ada@example.test', 'password' => 'a-strong-password'])->assertOk();
        $token = $login->json('meta.token');
        $this->assertIsString($token);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/v2-protected')->assertOk()->assertJson(['id' => $user->id]);
        $user->tokens()->update(['revoked' => true]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/v2-protected')->assertUnauthorized();
    }

    public function test_activitylog_5_works_with_generated_models_and_new_schema(): void
    {
        if (!class_exists(\Src\Infrastructure\AbstractModels\BaseModel::class, false)) {
            $this->loadStub('Infrastructure/AbstractModels/BaseModel.stub');
        }
        (require __DIR__.'/../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub')->up();
        $record = new class extends \Src\Infrastructure\AbstractModels\BaseModel {
            use \Spatie\Activitylog\Models\Concerns\LogsActivity;
            protected $table = 'users';
            protected $guarded = [];
            public function getActivitylogOptions(): \Spatie\Activitylog\Support\LogOptions
            {
                return \Spatie\Activitylog\Support\LogOptions::defaults()->logOnly(['name']);
            }
        };
        $record->forceFill(['name' => 'Before', 'email' => 'audit@example.test', 'password' => 'not-logged'])->save();
        $record->name = 'After';
        $record->save();
        $activity = \Spatie\Activitylog\Models\Activity::where('event', 'updated')->firstOrFail();
        $this->assertSame('Before', $activity->attribute_changes['old']['name']);
        $this->assertSame('After', $activity->attribute_changes['attributes']['name']);
        $this->assertStringNotContainsString('not-logged', $activity->toJson());
    }
}

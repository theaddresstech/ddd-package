<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Layouts\LayoutFactory;
use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FirstDomain extends Maker
{
    /**
     * Options to be available once Command-Type is called
     *
     * @return Array
     */
    public $options = [];

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
     * Fill all placeholders in the stub file
     *
     * @return Boll
     */
    public function service(Array $values):Bool{

        $values['name']       = 'user';
        $values['domain']     = 'User';

        // Create Entity
        Domain::createService([
            'name'      => $values['name'],
        ]);

        Crud::createService([
            'name'        => $values['name'],
            'domain'      => $values['domain'],
        ]);

        // Replace Entity
        $this->save(
            Path::toDomain($values['domain'],'Entities'),
            'User',
            'php',
            $this->getStub('first_domain-entity')
        );

        // Replace Migrations (users, reset-password)
        $this->replaceMigrations($values);

        // Create Auth Controllers
        $this->createAuthControllers($values);
        $this->modiyRouteServiceProvider();

        // Build Layout
        $this->buildLayout();


        $this->createGeneralDomain();
        $this->modifyMigrationSeeder();
        return true;

    }

    private function replaceMigrations($values){
        $factory_dir = Path::toDomain($values['domain'],'Database','Factories');
        $seeder_dir = Path::toDomain($values['domain'],'Database','Seeds');
        $migration_dir = Path::toDomain($values['domain'],'Database','Migrations');
        $migration_file = Path::files('src','Domain',$values['domain'],'Database','Migrations')[0];
        File::put(Path::build($migration_dir,'2013'.substr($migration_file, 4).'.php'),$this->getStub('first_domain-user-migration'));
        File::delete(Path::build($migration_dir,$migration_file.'.php'));

        File::copy(Path::stub($values['domain'],'2014_10_12_100000_create_password_resets_table.stub'),Path::build($migration_dir,'2014_10_12_100000_create_password_resets_table.php'));
        File::copy(Path::stub($values['domain'],'2019_08_19_000000_create_failed_jobs_table.stub'),Path::build($migration_dir,'2019_08_19_000000_create_failed_jobs_table.php'));

        // Seeder & Factory
        File::put(Path::build($seeder_dir,'UserTableSeeder.php'),$this->getStub('first_domain-user-seeder'));
        File::put(Path::build($factory_dir,'UserFactory.php'),$this->getStub('first_domain-user-factory'));

        if (config('ddd.scaffold.force')) {
            foreach (File::files(base_path('database/migrations')) as $migration) {
                File::delete($migration->getPathname());
            }
        }
    }

    private function createAuthControllers($values){
        File::copyDirectory(Path::stub($values['domain'],'Auth'),Path::toDomain($values['domain'],'Http','Controllers','Auth'));
        //File::copyDirectory(Path::stub($values['domain'],'auth-view','auth'),Path::toDomain($values['domain'],'Resources','Views','user','auth'));
        File::append(Path::toDomain($values['domain'],'Routes','api','guest.php'),"\nRoute::post('/login', \\Src\\Domain\\User\\Http\\Controllers\\Auth\\LoginController::class)->middleware('throttle:10,1');\n");



    }

    private function buildLayout(){
        [$layout,$options] = config('ddd.layout');

        $layout = LayoutFactory::create($layout,$options);

        if ($layout) {
            $layout->build();
        }
    }

    private function createGeneralDomain(){
        Domain::createService([
            'name'      => 'dashboard',
        ]);

        $controller = ['Dashboard','Http','Controllers','DashboardController.php'];
        //$resource = ['Dashboard','Resources','Views','dashboard','index.blade.php'];
        $route = ['Dashboard','Routes','web','auth.php'];

        $controller2 = ['Dashboard','Http','Controllers','ConfigureDomainController.php'];
        //$resource = ['Dashboard','Resources','Views','dashboard','index.blade.php'];
        $route2 = ['Dashboard','Routes','api','auth.php'];

        $request = ['Dashboard','Http','Requests','ConfigureDomain','ConfigureDomainFormRequest.php'];

        File::makeDirectory(Path::toDomain('Dashboard','Http','Controllers'),0755,true);
        File::put(Path::toDomain(...$controller), File::get(Path::stub(...$controller)));
        File::put(Path::toDomain(...$controller2), File::get(Path::stub(...$controller2)));

        //File::makeDirectory(Path::toDomain('Dashboard','Resources','Views','dashboard'),0755,true);
        //File::put(Path::toDomain(...$resource), File::get(Path::stub(...$resource)));

        File::put(Path::toDomain(...$route), File::get(Path::stub(...$route)));
        File::put(Path::toDomain(...$route2), File::get(Path::stub(...$route2)));

        File::makeDirectory(Path::toDomain('Dashboard','Http','Requests','ConfigureDomain'),0755,true);
        File::put(Path::toDomain(...$request), File::get(Path::stub(...$request)));
    }

    public function modifyMigrationSeeder(){
        $paths = [
            database_path('seeders/DatabaseSeeder.php'),
            database_path('seeds/DatabaseSeeder.php'),
        ];
        $from = [
            '// \App\Models\User::factory(10)->create();',
            '$this->call(\App\Domain\User\Database\Seeds\UserTableSeeder::class);',
        ];
        $to = '$this->call(\\Src\\Domain\\User\\Database\\Seeds\\UserTableSeeder::class);';

        foreach ($paths as $seederPath) {
            if (!File::isFile($seederPath)) {
                continue;
            }
            $content = File::get($seederPath);
            $updated = str_replace($from, $to, $content);
            if ($updated !== $content) {
                File::put($seederPath, $updated);
            }
        }
    }

    public function modiyRouteServiceProvider(){
        // Route Service Provider
        File::put(
            Path::toDomain('User','Providers','RouteServiceProvider.php'),
            $this->getStub('first_domain-user-route-service-provider',true)
        );
    }

}

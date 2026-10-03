<?php

namespace theaddresstechnology\DDD;

use theaddresstechnology\DDD\Helper\ArrayFormatter;
use theaddresstechnology\DDD\Helper\Make\Types\Domain;
use theaddresstechnology\DDD\Helper\Make\Types\FirstDomain;
use theaddresstechnology\DDD\Helper\Path;
use theaddresstechnology\DDD\Helper\SafePath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use theaddresstechnology\DDD\Helper\Stub;
use Illuminate\Support\Facades\Artisan;


/**
 * Generate Main DDD Direcotry-Sturcutre
 */
class Directory extends Command
{
    use Stub;

    private $base = "src";

    /**
     * Contains the base structure after converting nested array to Dot formate
     *
     * @var array
     */
    private $filesystem = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ddd:directory
    {--force : Rewrite bootstrap, routes, auth config, and the src tree}
    {--withoutBackup : Do not copy an existing src directory to backup/}
    {--removeBackup : Delete backup/ after a successful rewrite}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change the current directroy structure to suit DDD application';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->filesystem=ArrayFormatter::dot(config('ddd.structure.base'));
        //dd($this->filesystem);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('force')) {
            $this->error('ddd:directory deletes src/, overwrites bootstrap and auth config, and can delete application migrations. Re-run with --force.');

            return 1;
        }

        foreach ([
            'src', 'backup', 'app/Models/User.php', 'routes/web.php', 'config/auth.php',
            'bootstrap/app.php', 'bootstrap/providers.php', 'database/migrations',
            'database/seeders/DatabaseSeeder.php', 'database/seeds/DatabaseSeeder.php',
            'navbar.json', 'public/layout-dist', 'resources/views',
        ] as $path) {
            SafePath::confine(base_path($path), base_path());
        }

        if (!$this->option('withoutBackup')) {
            $this->backupSrc();
        }

        $previousForce = config('ddd.scaffold.force');
        config(['ddd.scaffold.force' => true]);
        try {
            $this->setupDirectory();
            $this->bootstrap();
            $this->firstDomain();
        } finally {
            config(['ddd.scaffold.force' => $previousForce]);
        }

        if ($this->option('removeBackup') && File::isDirectory(base_path('backup'))) {
            File::deleteDirectory(base_path('backup'));
        }

        return 0;
    }

    private function backupSrc(): void
    {
        $src = base_path('src');

        if (!File::isDirectory($src)) {
            return;
        }

        $destination = base_path('backup'.DIRECTORY_SEPARATOR.date('YmdHis').'-'.bin2hex(random_bytes(4)));
        if (!File::makeDirectory($destination, 0755, true) || !File::copyDirectory($src, $destination)) {
            throw new \RuntimeException('Backup failed; scaffolding was not started.');
        }
        $this->info('Backed up src/ to '.$destination);
    }

    /**
     * Create Domain sub-directories
     *
     * @return void
     */
    private function setupDirectory(){

        $src = base_path($this->base);

        if(File::isDirectory($src)){
            File::deleteDirectory($src);
        }

        foreach($this->filesystem as $folder => $files){

            $folder = str_replace('.',DIRECTORY_SEPARATOR,$folder);

            File::makeDirectory($src.DIRECTORY_SEPARATOR.$folder,0755, true, true);

            foreach($files as $file){
                //$this->info("started adding file name ".$file."to Folder ".$folder);
                $destination = $src.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$file;

                $stub = File::get(__DIR__.'/../stub/'.$folder.'/'.basename($file,'.php').'.stub');

                File::put($destination,$stub);
                //$this->info("finished adding file name ".$file."to Folder ".$folder);
            }
        }
        File::makeDirectory($src.DIRECTORY_SEPARATOR."Common".DIRECTORY_SEPARATOR."Resources".DIRECTORY_SEPARATOR."Views",0755, true, true);

        $userModel = app_path().DIRECTORY_SEPARATOR."Models".DIRECTORY_SEPARATOR."User.php";
        if (File::isFile($userModel)) {
            File::delete($userModel);
        }


        //$this->info("started adding routesss ");

        File::put(base_path('routes').DIRECTORY_SEPARATOR.'web.php',$this->getStub('route-web'));

        //$this->info("finished adding routesss ");
    }

    /**
     * Create Configuration files
     *
     * @return void
     */
    private function bootstrap(){

        // set config auth
        File::put(config_path('auth.php'),$this->getStub('config-auth'));

        //set providers
        File::put(base_path('bootstrap').DIRECTORY_SEPARATOR.'providers.php',File::get(__DIR__.'/../stub/Bootstrap/providers.stub'));

        // Set Bootstrap
        File::put(base_path('bootstrap').DIRECTORY_SEPARATOR.'app.php',File::get(__DIR__.'/../stub/Bootstrap/app.stub'));

    }

    /**
     * Generate User Domain
     *
     * @return void
     */
    private function firstDomain(){

        File::delete(base_path('navbar.json'));

        FirstDomain::createService([]);


        $layout = config('ddd.layout')[0] ?? null;

        foreach (['Navbar' => 'navbar', 'Header' => 'header', 'Footer' => 'footer'] as $component => $folder) {
            $destination = rtrim(Path::toCommon('Components', $component), DIRECTORY_SEPARATOR);
            $source = Path::build(Path::package(), 'views', (string) $layout, $folder);

            if (!is_string($layout) || !File::isDirectory($source)) {
                $this->warn('Layout folder "'.$folder.'" is not in this package. Skipped '.$component.'.');
                continue;
            }

            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            File::copyDirectory($source, $destination);
        }

    }


    public function setupJS(){
        File::put(resource_path('js/app.js'),$this->getStub('app-js'));
    }
}

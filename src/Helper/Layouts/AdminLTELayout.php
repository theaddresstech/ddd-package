<?php

namespace theaddresstechnology\DDD\Helper\Layouts;

use theaddresstechnology\DDD\Helper\Path;
use Illuminate\Support\Facades\File;

class AdminLTELayout extends Layout{

    /**
     * Specify the directory name of the layout-view
     *
     * @param string $viewName
     */
    protected $viewName = 'lte';

    /**
     * Create layout and files for the current template
     *
     * @return Bool
     */
    function build() : Bool{
        $dir = public_path('layout-dist');


        $dist = Path::build(Path::package(),'views','lte','dist');
        $layout = Path::build(Path::package(),'views','lte','layout');

        if(!File::isDirectory($dist) || !File::isDirectory($layout)){
            return false;
        }

        if(File::isDirectory($dir)){
            File::deleteDirectory($dir);
        }

        File::makeDirectory($dir);

        File::copyDirectory($dist,$dir);
        File::copyDirectory($layout,resource_path('views'));

        return true;
    }
}

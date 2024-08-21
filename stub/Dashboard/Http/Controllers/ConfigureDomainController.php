<?php

namespace Src\Domain\Dashboard\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Src\Domain\Dashboard\Http\Requests\ConfigureDomain\ConfigureDomainFormRequest;
use Src\Infrastructure\Http\AbstractControllers\BaseController as Controller;
use theaddresstechnology\DDD\Helper\NamespaceCreator;
use theaddresstechnology\DDD\Helper\Path;
use theaddresstechnology\DDD\Traits\Responder;

class ConfigureDomainController extends Controller
{
    use Responder;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data=Path::getDomains();

        $data=collect($data)->map(function ($name) {
            $service_provider = NamespaceCreator::Segments("Src", "Domain", $name, "Providers", "DomainServiceProvider");
            $app = File::get(base_path('bootstrap' . DIRECTORY_SEPARATOR . 'providers.php'));
            $status="in_active";
            if (Str::of($app)->contains([$service_provider], [false]) == true) {
                $status="active";
            }
            return[
                "name"=>$name,
                'status'=>$status
            ];
        });

        $this->setApiResponse(fn () => response()->json(['modules' =>$data]));

        return $this->response();
    }

    public function enable(ConfigureDomainFormRequest $request){
        $name=$request->validated()['name'];

        Artisan::call("ddd:make",['type'=>'enabledomain',"--domain"=>$name]);

        $this->setApiResponse(fn () => response()->json(['message' =>"Domain is Enabled"]));

        return $this->response();
    }

    public function disable(ConfigureDomainFormRequest $request){
        $name=$request->validated()['name'];

        Artisan::call("ddd:make",['type'=>'disabledomain',"--domain"=>$name]);

        $this->setApiResponse(fn () => response()->json(['message' =>"Domain is Disabled"]));

        return $this->response();
    }

}

<?php
use Illuminate\Support\Facades\Route;








Route::get("get_domain","ConfigureDomainController@index");

Route::post("enable_domain","ConfigureDomainController@enable");

Route::post("disable_domain","ConfigureDomainController@disable");

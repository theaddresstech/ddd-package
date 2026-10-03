<?php

use Illuminate\Support\Facades\Route;
use Src\Domain\Dashboard\Http\Controllers\ConfigureDomainController;

Route::middleware(['auth:api', 'can:manage-domains'])->group(function () {
    Route::get('get_domain', [ConfigureDomainController::class, 'index']);
    Route::post('enable_domain', [ConfigureDomainController::class, 'enable']);
    Route::post('disable_domain', [ConfigureDomainController::class, 'disable']);
});

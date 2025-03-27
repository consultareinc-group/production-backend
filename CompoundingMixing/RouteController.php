<?php

namespace App\Http\Controllers\ProductionManagementSystem\CompoundingMixing;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;

class RouteController extends Controller
{
    /**
     * Register routes for this module.
     */
    public static function moduleRoute()
    {
        //change  module-name based on your desired path
        Route::prefix('compounding-mixing')->group(function () {
            Route::get('/{id?}', [ApiController::class, 'get']); 
            Route::post('/', [ApiController::class, 'post']); 
            Route::put('/{id}', [ApiController::class, 'put']); 
            Route::delete('/{id}', [ApiController::class, 'delete']); 
            Route::post('/{id}', [ApiController::class, 'upload']); 
        });

    }
}
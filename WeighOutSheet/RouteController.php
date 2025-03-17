<?php

namespace App\Http\Controllers\ProductionManagementSystem\WeighOutSheet;

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
        Route::prefix('weigh-out-sheet')->group(function () {
            Route::get('/{id?}', [ApiController::class, 'get']); 
            Route::post('/', [ApiController::class, 'post']); 
            Route::post('/{id}', [ApiController::class, 'put']); 
            Route::delete('/{id}', [ApiController::class, 'delete']); 
        });

    }
}
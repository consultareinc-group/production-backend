<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller; 
use App\Http\Controllers\ProductionManagementSystem\CompoundingMixing\RouteController as CompoundingMixingRouteController;

class SystemRouteController extends Controller
{
    public static function registerRoutes()
    {

        //rename system-name the system name and ApiController to Module API Controller 
        Route::prefix('production-management')->middleware(['sanitize-request'])->group(function () {
            
            CompoundingMixingRouteController::moduleRoute();

            // Add other routes for other ApiController as needed
        });

    }
}

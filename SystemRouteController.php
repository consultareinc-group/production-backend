<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller; 

use App\Http\Controllers\ProductionManagementSystem\PreOperationVerification\RouteController as PreOperationVerificationRouteController;
// use App\Http\Controllers\ProductionManagementSystem\CompoundingMixing\RouteController as CompoundingMixingRouteController;
// use App\Http\Controllers\ProductionManagementSystem\Equipment\RouteController as EquipmentRouteController;
// use App\Http\Controllers\ProductionManagementSystem\Processing\RouteController as ProcessingRouteController;
// use App\Http\Controllers\ProductionManagementSystem\WeighOutSheet\RouteController as WeighOutSheetRouteController;

class SystemRouteController extends Controller
{
    public static function registerRoutes()
    {

        //rename system-name the system name and ApiController to Module API Controller 
        Route::prefix('production-management')->middleware(['sanitize-request','jwt', 'user-permission'])->group(function () {
            
            PreOperationVerificationRouteController::moduleRoute();
            // CompoundingMixingRouteController::moduleRoute();
            // EquipmentRouteController::moduleRoute();
            // ProcessingRouteController::moduleRoute();
            // WeighOutSheetRouteController::moduleRoute();

            // Add other routes for other ApiController as needed
        });

    }
}

<?php

/**
 *
 * replace the SystemName based on the Folder
 *
 */

namespace App\Http\Controllers\CigProduction;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CigProduction\ProductionPlanController;

class RouteController extends Controller {
    public static function registerRoutes() {

        //rename system-name the system name and ApiController to Module API Controller
        Route::prefix('cig-production')->group(function () {
            Route::get('/production-plan/production-planning/{id?}', [ProductionPlanController::class, 'getPlan']);
            Route::post('/production-plan/production-planning', [ProductionPlanController::class, 'postPlan']);
            Route::put('/production-plan/production-planning/{id}', [ProductionPlanController::class, 'editPlanDetails']);
            Route::put('/production-plan/production-planning/edit-status/{id}', [ProductionPlanController::class, 'editPlanStatus']);
            Route::put('/production-plan/production-planning/archive/{id}', [ProductionPlanController::class, 'archiveProductionPlan']); //archive is an extra verb, and the verb is not included in the REQUEST VERBS (GET, POST, PUT, DELETE)

            //NEW ROUTE NAMING CONVENTION - rename  'api' to /module-name/tablename/extra verb if the verb is not included in the REQUEST VERBS (GET, POST, PUT, DELETE)
        });
    }
}

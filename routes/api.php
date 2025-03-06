<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AviationstackController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Aviationstack API Routes
Route::prefix('aviation')->group(function () {
    // Test API connection
    Route::get('/test', [AviationstackController::class, 'test']);

    Route::get('/flights', [AviationstackController::class, 'flights']);
    Route::get('/routes', [AviationstackController::class, 'routes']);
    Route::get('/airports', [AviationstackController::class, 'airports']);
    Route::get('/airlines', [AviationstackController::class, 'airlines']);
    Route::get('/airplanes', [AviationstackController::class, 'airplanes']);
    Route::get('/aircraft-types', [AviationstackController::class, 'aircraftTypes']);
    Route::get('/taxes', [AviationstackController::class, 'taxes']);
    Route::get('/cities', [AviationstackController::class, 'cities']);
    Route::get('/countries', [AviationstackController::class, 'countries']);
    Route::get('/flight-schedules', [AviationstackController::class, 'flightSchedules']);
    Route::get('/future-schedules', [AviationstackController::class, 'futureSchedules']);
});

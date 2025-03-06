<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

// Direct test of Aviationstack API
Route::get('/api-test', function () {
    $apiKey = env('AVIATIONSTACK_API_KEY', '3f73618bf41df1cbd0cc28f50ed27670');
    $response = Http::get("http://api.aviationstack.com/v1/flights", [
        'access_key' => $apiKey,
        'limit' => 1
    ]);

    // Log the response for debugging
    $logPath = storage_path('logs');
    if (!file_exists($logPath)) {
        mkdir($logPath, 0755, true);
    }
    $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
    fwrite($debugLog, "\n" . date('Y-m-d H:i:s') . " - Direct API Test\n");
    fwrite($debugLog, "Status: " . $response->status() . "\n");
    fwrite($debugLog, "Body: " . substr($response->body(), 0, 1000) . "\n\n");
    fclose($debugLog);

    return response()->json([
        'status' => $response->status(),
        'data' => $response->json(),
        'api_key_used' => substr($apiKey, 0, 5) . '...',
        'url' => "http://api.aviationstack.com/v1/flights?access_key={$apiKey}&limit=1"
    ]);
});

// Direct access to Aviationstack controller
Route::get('/aviation-test', [\App\Http\Controllers\Api\AviationstackController::class, 'test']);

// Register API routes in web routes since api.php routes aren't working
Route::prefix('api/aviation')->group(function () {
    Route::get('/test', [\App\Http\Controllers\Api\AviationstackController::class, 'test']);
    Route::get('/flights', [\App\Http\Controllers\Api\AviationstackController::class, 'flights']);
    Route::get('/routes', [\App\Http\Controllers\Api\AviationstackController::class, 'routes']);
    Route::get('/airports', [\App\Http\Controllers\Api\AviationstackController::class, 'airports']);
    Route::get('/airlines', [\App\Http\Controllers\Api\AviationstackController::class, 'airlines']);
    Route::get('/airplanes', [\App\Http\Controllers\Api\AviationstackController::class, 'airplanes']);
    Route::get('/aircraft-types', [\App\Http\Controllers\Api\AviationstackController::class, 'aircraftTypes']);
    Route::get('/taxes', [\App\Http\Controllers\Api\AviationstackController::class, 'taxes']);
    Route::get('/cities', [\App\Http\Controllers\Api\AviationstackController::class, 'cities']);
    Route::get('/countries', [\App\Http\Controllers\Api\AviationstackController::class, 'countries']);
    Route::get('/flight-schedules', [\App\Http\Controllers\Api\AviationstackController::class, 'flightSchedules']);
    Route::get('/future-schedules', [\App\Http\Controllers\Api\AviationstackController::class, 'futureSchedules']);
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

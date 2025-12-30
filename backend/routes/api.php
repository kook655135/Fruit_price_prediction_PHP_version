<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FruitDataController;

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


 // 數據維護專用 API 組
Route::prefix('v1/maintenance')->group(function () {
    // 價格預測數據
    Route::get('prices', [FruitDataController::class, 'getPriceIndex']);      // 查詢
    Route::post('prices', [FruitDataController::class, 'storePrice']);        // 新增
    Route::put('prices', [FruitDataController::class, 'updatePrice']);        // 修改
    Route::delete('prices', [FruitDataController::class, 'deletePrice']);     // 刪除
});Route::get('/v1/maintenance/crops', [App\Http\Controllers\Api\FruitApiController::class, 'getCrops']);
Route::get('/v1/maintenance/prices', [App\Http\Controllers\Api\FruitApiController::class, 'getPrices']);
Route::get('/v1/maintenance/crops', [App\Http\Controllers\Api\FruitApiController::class, 'getCrops']);
Route::get('/v1/maintenance/prices', [App\Http\Controllers\Api\FruitApiController::class, 'getPrices']);
Route::get('/v1/maintenance/crops', [App\Http\Controllers\Api\FruitApiController::class, 'getCrops']);
Route::get('/v1/maintenance/prices', [App\Http\Controllers\Api\FruitApiController::class, 'getPrices']);

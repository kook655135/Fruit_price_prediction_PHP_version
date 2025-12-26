<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FruitPriceController;

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

Route::get('/fruit-price', [FruitPriceController::class, 'getFruitPrice']);

/* 待補齊的 CUD 路由 (演示後再實作)
 Route::post('/fruit-price', [FruitPriceController::class, 'store']);
 Route::put('/fruit-price/{id}', [FruitPriceController::class, 'update']);
 Route::delete('/fruit-price/{id}', [FruitPriceController::class, 'destroy']);
 */
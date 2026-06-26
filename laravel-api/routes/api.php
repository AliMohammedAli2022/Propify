<?php

use App\Http\Controllers\Api\PropifyController;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', [PropifyController::class, 'options'])->where('any', '.*');

Route::get('/health', [PropifyController::class, 'health']);
Route::get('/dashboard', [PropifyController::class, 'dashboard']);
Route::get('/properties', [PropifyController::class, 'properties']);
Route::post('/properties', [PropifyController::class, 'storeProperty']);
Route::get('/clients', [PropifyController::class, 'clients']);
Route::post('/clients', [PropifyController::class, 'storeClient']);
Route::get('/contracts', [PropifyController::class, 'contracts']);
Route::post('/contracts', [PropifyController::class, 'storeContract']);
Route::get('/installments', [PropifyController::class, 'installments']);
Route::get('/vouchers', [PropifyController::class, 'vouchers']);
Route::post('/vouchers', [PropifyController::class, 'storeVoucher']);
Route::get('/ledger', [PropifyController::class, 'ledger']);

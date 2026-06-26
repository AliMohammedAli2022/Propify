<?php

use App\Http\Controllers\Api\PropifyController;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', [PropifyController::class, 'options'])->where('any', '.*');

Route::post('/auth/login', [PropifyController::class, 'login']);
Route::get('/auth/me', [PropifyController::class, 'me']);
Route::post('/auth/logout', [PropifyController::class, 'logout']);
Route::get('/health', [PropifyController::class, 'health']);
Route::get('/dashboard', [PropifyController::class, 'dashboard']);
Route::get('/users', [PropifyController::class, 'users']);
Route::post('/users', [PropifyController::class, 'storeUser']);
Route::get('/properties', [PropifyController::class, 'properties']);
Route::post('/properties', [PropifyController::class, 'storeProperty']);
Route::get('/properties/{property:code}/media', [PropifyController::class, 'propertyMedia']);
Route::post('/properties/{property:code}/media', [PropifyController::class, 'storePropertyMedia']);
Route::get('/clients', [PropifyController::class, 'clients']);
Route::post('/clients', [PropifyController::class, 'storeClient']);
Route::get('/contracts', [PropifyController::class, 'contracts']);
Route::post('/contracts', [PropifyController::class, 'storeContract']);
Route::get('/installments', [PropifyController::class, 'installments']);
Route::get('/vouchers', [PropifyController::class, 'vouchers']);
Route::post('/vouchers', [PropifyController::class, 'storeVoucher']);
Route::get('/ledger', [PropifyController::class, 'ledger']);
Route::get('/reports/financial', [PropifyController::class, 'financialReport']);
Route::get('/reports/properties', [PropifyController::class, 'propertiesReport']);
Route::get('/reports/installments', [PropifyController::class, 'installmentsReport']);
Route::get('/reports/employee-performance', [PropifyController::class, 'employeePerformanceReport']);

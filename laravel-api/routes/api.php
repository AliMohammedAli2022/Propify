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
Route::put('/users/{user}', [PropifyController::class, 'updateUser']);
Route::delete('/users/{user}', [PropifyController::class, 'deleteUser']);
Route::get('/properties', [PropifyController::class, 'properties']);
Route::post('/properties', [PropifyController::class, 'storeProperty']);
Route::put('/properties/{property:code}', [PropifyController::class, 'updateProperty']);
Route::delete('/properties/{property:code}', [PropifyController::class, 'deleteProperty']);
Route::post('/properties/{property:code}/approve', [PropifyController::class, 'approveProperty']);
Route::get('/properties/{property:code}/media', [PropifyController::class, 'propertyMedia']);
Route::post('/properties/{property:code}/media', [PropifyController::class, 'storePropertyMedia']);
Route::get('/clients', [PropifyController::class, 'clients']);
Route::post('/clients', [PropifyController::class, 'storeClient']);
Route::put('/clients/{client}', [PropifyController::class, 'updateClient']);
Route::delete('/clients/{client}', [PropifyController::class, 'deleteClient']);
Route::get('/contracts', [PropifyController::class, 'contracts']);
Route::post('/contracts', [PropifyController::class, 'storeContract']);
Route::put('/contracts/{contract:code}', [PropifyController::class, 'updateContract']);
Route::delete('/contracts/{contract:code}', [PropifyController::class, 'deleteContract']);
Route::get('/contracts/{contract:code}/print', [PropifyController::class, 'printContract']);
Route::get('/installments', [PropifyController::class, 'installments']);
Route::post('/installments/{installment}/pay', [PropifyController::class, 'payInstallment']);
Route::get('/vouchers', [PropifyController::class, 'vouchers']);
Route::post('/vouchers', [PropifyController::class, 'storeVoucher']);
Route::put('/vouchers/{voucher:code}', [PropifyController::class, 'updateVoucher']);
Route::delete('/vouchers/{voucher:code}', [PropifyController::class, 'deleteVoucher']);
Route::get('/vouchers/{voucher:code}/print', [PropifyController::class, 'printVoucher']);
Route::get('/ledger', [PropifyController::class, 'ledger']);
Route::get('/notifications', [PropifyController::class, 'notifications']);
Route::get('/activity-logs', [PropifyController::class, 'activityLogs']);
Route::get('/settings', [PropifyController::class, 'settings']);
Route::put('/settings', [PropifyController::class, 'updateSettings']);
Route::get('/reports/financial', [PropifyController::class, 'financialReport']);
Route::get('/reports/properties', [PropifyController::class, 'propertiesReport']);
Route::get('/reports/installments', [PropifyController::class, 'installmentsReport']);
Route::get('/reports/employee-performance', [PropifyController::class, 'employeePerformanceReport']);

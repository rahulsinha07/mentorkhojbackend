<?php

use App\Http\Controllers\Admin\InvoiceAdminController;
use App\Http\Controllers\Admin\InvoiceSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Admin session-authenticated JSON mirrors (same controllers as web admin routes).
| Primary UI uses /admin/invoices/* — these exist for programmatic admin clients.
*/
Route::group(['prefix' => 'admin/invoices', 'middleware' => ['web', 'admin', 'employee_active_check', 'module:invoice_management']], function () {
    Route::get('settings', [InvoiceSettingsController::class, 'edit']);
    Route::put('settings', [InvoiceSettingsController::class, 'update']);
    Route::get('list', [InvoiceAdminController::class, 'index']);
    Route::post('/', [InvoiceAdminController::class, 'store']);
    Route::post('calculate', [InvoiceAdminController::class, 'calculate']);
    Route::get('{id}', [InvoiceAdminController::class, 'show']);
    Route::put('{id}', [InvoiceAdminController::class, 'update']);
    Route::delete('{id}', [InvoiceAdminController::class, 'destroy']);
    Route::get('{id}/pdf', [InvoiceAdminController::class, 'pdf']);
});

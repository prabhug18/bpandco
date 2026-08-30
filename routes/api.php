<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateExternalApi;
use App\Http\Controllers\Api\ExternalApiController;

Route::middleware([AuthenticateExternalApi::class])->prefix('v1/external')->group(function () {
    Route::post('bulk-sync', [ExternalApiController::class, 'bulkSync'])->name('api.v1.external.bulk-sync');
    Route::get('sync-status/{reference_id}', [ExternalApiController::class, 'syncStatus'])->name('api.v1.external.sync-status');
    Route::get('slips/approved', [ExternalApiController::class, 'approvedSlips'])->name('api.v1.external.slips.approved');
    Route::get('metrics', [ExternalApiController::class, 'metrics'])->name('api.v1.external.metrics');
    Route::get('users', [ExternalApiController::class, 'users'])->name('api.v1.external.users');
});

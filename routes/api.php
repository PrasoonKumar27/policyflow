<?php

use App\Http\Controllers\MockInsurerController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow REST API Contract
|--------------------------------------------------------------------------
| Built for fault-tolerant travel insurance intake, issuance & reconciliation.
*/

Route::prefix('policy-requests')->group(function () {
    Route::get('/', [PolicyRequestController::class, 'index']);
    Route::post('/', [PolicyRequestController::class, 'store']);
    Route::get('/{id}', [PolicyRequestController::class, 'show']);
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit']);
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile']);
});

Route::prefix('mock-insurer')->group(function () {
    Route::post('/callback', [MockInsurerController::class, 'callback']);
    Route::get('/policies/{provider_reference}', [MockInsurerController::class, 'queryPolicy']);
    Route::post('/simulate-callback', [MockInsurerController::class, 'triggerCallback']);
});

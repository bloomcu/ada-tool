<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use DDD\Http\Sites\SiteController;
use DDD\Http\Scans\StatusController;
use DDD\Http\Scans\ScanController;
use DDD\Http\Scans\DataSetController;
use DDD\Http\Scans\AbortRunController;

// Scans
Route::prefix('scans')->group(function() {
    Route::get('/', [ScanController::class, 'store']);
    Route::get('/status/{evaluation}', [StatusController::class, 'status']);
    Route::get('/dataset', [DataSetController::class, 'dataset']);
    Route::get('/abortrun/{evaluation}', [AbortRunController::class, 'abortRun']);
});

Route::middleware('auth:sanctum')->group(function() {
    Route::prefix('{organization:slug}')->scopeBindings()->group(function() {

        // Sites
        Route::prefix('sites')->group(function() {
            Route::get('/', [SiteController::class, 'index']);
            Route::post('/', [SiteController::class, 'store']);
            Route::get('/{site}', [SiteController::class, 'show']);
            Route::put('/{site}', [SiteController::class, 'update']);
            Route::delete('/{site}', [SiteController::class, 'destroy']);
        });
    });
});
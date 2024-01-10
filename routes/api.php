<?php

use Illuminate\Support\Facades\Route;
use DDD\Http\Sites\SiteScanController;
use DDD\Http\Sites\SiteController;
use DDD\Http\Scans\StatusController;
use DDD\Http\Scans\ScanImportController;
use DDD\Http\Scans\ScanController;
use DDD\Http\Scans\DataSetController;
use DDD\Http\Scans\AbortRunController;
use DDD\Http\Pages\PageController;

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

            // Scan site
            Route::post('/{site}/scan', [SiteScanController::class, 'store']);
        });

        // Scans
        Route::prefix('scans')->group(function() {
            Route::get('/', [ScanController::class, 'index']);
            Route::get('/{scan}', [ScanController::class, 'show']);

            // Active scans
            Route::get('/{scan}/status', [StatusController::class, 'show']); // Check status on Apify
            Route::get('/{scan}/dataset', [DataSetController::class, 'show']); // Show dataset from Apify
            Route::get('/{scan}/abort', [AbortRunController::class, 'abortRun']); // Abort run on Apify
            Route::get('/{scan}/import', [ScanImportController::class, 'import']); // Abort run on Apify
        });

        // Pages
        Route::prefix('scans/{scan}/pages')->group(function() {
            Route::get('/{page}', [PageController::class, 'show']);
        });
    });
});
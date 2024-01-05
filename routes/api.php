<?php

use DDD\Http\Scans\AbortRunController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use DDD\Http\Scans\ScanController;
use DDD\Http\Scans\StatusController;
use DDD\Http\Scans\DataSetController;

// Scans
Route::prefix('scans')->group(function() {
    Route::get('/', [ScanController::class, 'store']);
    Route::get('/status', [StatusController::class, 'status']);
    Route::get('/dataset', [DataSetController::class, 'dataset']);
    Route::get('/abortrun', [AbortRunController::class, 'abortRun']);
   
});
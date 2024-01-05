<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use DDD\Http\Scans\ScanController;

// Scans
Route::prefix('scans')->group(function() {
    Route::post('/', [ScanController::class, 'store']);
});
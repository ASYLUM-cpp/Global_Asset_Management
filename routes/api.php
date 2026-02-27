<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (REQ-05: API-Based File Import)
|--------------------------------------------------------------------------
| Authenticated via Sanctum Bearer token.
| All routes prefixed with /api automatically.
*/

Route::middleware('auth:sanctum')->group(function () {
    // Asset import (async — returns 202 with staging_id)
    Route::post('/assets/import', [ApiController::class, 'import'])->name('api.assets.import');

    // Check import / pipeline status
    Route::get('/assets/status/{id}', [ApiController::class, 'status'])->name('api.assets.status');

    // Get single asset details
    Route::get('/assets/{id}', [ApiController::class, 'show'])->name('api.assets.show');

    // List assets (paginated)
    Route::get('/assets', [ApiController::class, 'index'])->name('api.assets.index');

    // Token info (whoami)
    Route::get('/user', [ApiController::class, 'user'])->name('api.user');
});

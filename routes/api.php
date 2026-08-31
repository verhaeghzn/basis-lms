<?php

use App\Http\Controllers\Api\OAuthConnectionController;
use App\Http\Controllers\Api\SampleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')
    ->prefix('v1')
    ->group(function (): void {
        Route::get('me', [OAuthConnectionController::class, 'show']);
        Route::delete('connection', [OAuthConnectionController::class, 'destroy']);
        Route::get('samples', [SampleController::class, 'index']);
        Route::get('samples/{sampleReference}', [SampleController::class, 'show']);
    });

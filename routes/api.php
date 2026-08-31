<?php

use App\Http\Controllers\Api\SampleController;
use App\Http\Middleware\AuthenticateSemphony;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateSemphony::class)
    ->prefix('v1')
    ->group(function (): void {
        Route::get('samples', [SampleController::class, 'index']);
        Route::get('samples/{sample:unique_ref}', [SampleController::class, 'show']);
    });

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// This is a headless REST service; the root simply advertises liveness.
// The framework's /up endpoint remains the target for health checks.
Route::get('/', fn () => response()->json([
    'service' => 'auth-service',
    'status' => 'ok',
]));

<?php

use Illuminate\Support\Facades\Route;

// The API is headless; the React SPA lives in ../frontend.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'health' => url('/api/v1/health'),
]));

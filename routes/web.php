<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'system' => 'Balsam Clinic API',
        'status' => 'running',
    ]);
});
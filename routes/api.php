<?php

use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::post('/farms', FarmController::class);
Route::match(['get', 'post'], '/farms/{code}', FarmController::class)->where('code', '[A-F0-9]{12}');

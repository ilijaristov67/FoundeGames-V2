<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YouTubeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/getTranscript', [YouTubeController::class, 'getTranscript']);
Route::get('/check', [YouTubeController::class, 'check']);
Route::get('/getVideo/{id}', [YouTubeController::class, 'show']);
Route::post('/search/{id}', [YouTubeController::class, 'search']);
Route::post('/searchAll', [YouTubeController::class, 'searchAll']);

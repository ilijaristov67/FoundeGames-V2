<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YouTubeController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/youtube', [YouTubeController::class, 'index'])->name('youtube.index');
Route::post('/youtube/transcript', [YouTubeController::class, 'getTranscript'])->name('getTranscript');

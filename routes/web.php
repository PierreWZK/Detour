<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/recherche', [SearchController::class, 'index'])->name('search');
Route::get('/adresses', [SearchController::class, 'addresses'])->middleware('throttle:60,1')->name('addresses');

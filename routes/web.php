<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/recherche', [SearchController::class, 'index'])->name('search');
Route::get('/adresses', [SearchController::class, 'addresses'])->middleware('throttle:60,1')->name('addresses');

Route::get('/jeu', [GameController::class, 'index'])->name('game');
Route::post('/jeu/juste-prix', [GameController::class, 'price'])->middleware('throttle:30,1')->name('game.price');
Route::post('/jeu/quiz', [GameController::class, 'quiz'])->middleware('throttle:30,1')->name('game.quiz');

<?php

use App\Http\Controllers\BinController;
use App\Http\Controllers\ClearBinRequestsController;
use App\Http\Controllers\ReplayCapturedRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

Route::post('/bins', [BinController::class, 'store'])
    ->middleware('throttle:bins')
    ->name('bins.store');

Route::get('/bins/{bin}', [BinController::class, 'show'])->name('bins.show');

Route::delete('/bins/{bin}/requests', ClearBinRequestsController::class)->name('bins.requests.clear');

Route::post('/requests/{capturedRequest}/replay', ReplayCapturedRequestController::class)
    ->middleware('throttle:replays')
    ->name('requests.replay');

<?php

use App\Http\Controllers\CaptureRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hook Routes
|--------------------------------------------------------------------------
|
| Public capture endpoints. These are registered outside the "web" group so
| third-party senders never touch sessions, cookies or CSRF verification.
|
*/

Route::any('/h/{bin}/{path?}', CaptureRequestController::class)
    ->where('path', '.*')
    ->name('hooks.capture');

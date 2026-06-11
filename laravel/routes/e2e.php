<?php

use App\Http\Controllers\E2eLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| E2E test-login seam
|--------------------------------------------------------------------------
| This file is loaded from bootstrap/app.php ONLY when the app runs in the
| local/testing environment with E2E_LOGIN set. It is never registered in
| production. See App\Http\Controllers\E2eLoginController for the rationale.
*/
Route::get('e2e/login', [E2eLoginController::class, 'login'])->name('e2e.login');

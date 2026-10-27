<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
| Authentication is handled by our REST API using Sanctum bearer tokens.
| Blade routes only serve frontend pages.
*/

Route::redirect('/', '/login')->name('home');

Route::view('/login', 'auth.login')
    ->name('login');

Route::view('/register', 'auth.register')
    ->name('register');

Route::view('/verify-email', 'auth.verify-email')
    ->name('verification.notice');

Route::view('/forgot-password', 'auth.forgot-password')
    ->name('password.request');


Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => request()->query('email', ''),
    ]);
})->name('password.reset');

/*
|--------------------------------------------------------------------------
| Application Pages
|--------------------------------------------------------------------------
| These routes serve Blade shells only.
| Authentication is verified through /api/auth/me.
| Sensitive data must never be embedded in these views.
*/

Route::view('/dashboard', 'dashboard.index')
    ->name('dashboard');

Route::view('/gis', 'gis.index')
    ->name('gis.index');


Route::view('/account-verification', 'auth.account-verification')
    ->name('account.verification');

Route::view('/livestock/monitoring', 'livestock.monitoring')
    ->name('livestock.monitoring');

Route::view('/analytics/prices', 'analytics.prices')
    ->name('analytics.prices');


Route::view('/analytics/transactions', 'analytics.transactions')
    ->name('analytics.transactions');


Route::view('/analytics/valuations', 'analytics.valuations')
    ->name('analytics.valuations');

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

Route::view('/farmers', 'farmers.index')
    ->name('farmers.index');


Route::get('/farmers/{farmer}', function (string $farmer) {
    return view('farmers.show', compact('farmer'));
})->name('farmers.show');


Route::view('/livestock', 'livestock.index')
    ->name('livestock.index');

Route::view('/admin/lgu-authorities', 'users.authorities')
    ->name('admin.lgu-authorities.index');

Route::view('/lgu/encoders', 'users.encoders')
    ->name('lgu.encoders.index');

Route::view('/users', 'users.index')
    ->name('admin.users.index');

Route::view('/lgu/encoders', 'lgu.encoders.index')
    ->name('lgu.encoders.index');

Route::view('/livestock/register', 'livestock.create')
    ->name('farmer.livestock.create');

Route::get('/livestock/{livestock}/edit', function (string $livestock) {
    return view('livestock.edit', ['livestockId' => $livestock]);
})->name('livestock.edit');

Route::get('/livestock/{livestock}', function (string $livestock) {
    return view('livestock.show', ['livestockId' => $livestock]);
})->name('livestock.show');

Route::view('/calculator', 'calculator.index')
    ->name('calculator.index');
<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('emails', [MailboxController::class, 'index'])->name('emails.index');
    Route::get('emails/{emailId}', [MailboxController::class, 'show'])
        ->whereNumber('emailId')
        ->name('emails.show');
});

require __DIR__.'/settings.php';

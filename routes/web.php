<?php

use App\Livewire\EmailModalComponent;
use App\Livewire\EmailSearchComponent;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('emails', EmailSearchComponent::class)->name('emails.index');
    Route::get('emails/{emailId}', EmailModalComponent::class)
        ->whereNumber('emailId')
        ->name('emails.show');
});

require __DIR__.'/settings.php';

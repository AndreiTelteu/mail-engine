<?php

use App\Http\Controllers\MailSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/mail');
    Route::get('settings/mail', [MailSettingsController::class, 'index'])->name('mail.settings');
    Route::post('settings/mail/test', [MailSettingsController::class, 'test'])->name('mail.settings.test');
    Route::put('settings/mail', [MailSettingsController::class, 'store'])->name('mail.settings.store');
    Route::post('settings/mail/sync', [MailSettingsController::class, 'startSync'])->name('mail.settings.sync');
});

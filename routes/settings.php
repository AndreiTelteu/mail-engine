<?php

use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\McpTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/mail');
    Route::get('settings/mail', [MailSettingsController::class, 'index'])->name('mail.settings');
    Route::post('settings/mail/test', [MailSettingsController::class, 'test'])->name('mail.settings.test');
    Route::put('settings/mail', [MailSettingsController::class, 'store'])->name('mail.settings.store');
    Route::post('settings/mail/sync', [MailSettingsController::class, 'startSync'])->name('mail.settings.sync');
    Route::post('settings/mail/mcp-tokens', [McpTokenController::class, 'store'])->name('mail.settings.mcp-tokens.store');
    Route::delete('settings/mail/mcp-tokens/{tokenId}', [McpTokenController::class, 'destroy'])
        ->whereNumber('tokenId')->name('mail.settings.mcp-tokens.destroy');
});

<?php

use App\Exceptions\Imap\ImapConnectionException;
use App\Livewire\ImapSettingsComponent;
use App\Models\User;
use App\Services\ImapConnectionService;
use Livewire\Livewire;
use Tests\Support\Fakes\ScriptedImapConnectionService;

test('settings configuration flow lets a user test and save imap settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imapService = new ScriptedImapConnectionService(
        testConnectionHandler: fn (string $hostname, int $port, string $username, string $password, ?string $encryption): bool => $hostname === 'imap.example.com'
            && $port === 993
            && $username === 'user@example.com'
            && $password === 'good-secret'
            && $encryption === 'ssl',
    );

    app()->instance(ImapConnectionService::class, $imapService);

    $this->get(route('mail.settings'))
        ->assertOk()
        ->assertSee('Mail settings');

    Livewire::test(ImapSettingsComponent::class)
        ->set('hostname', 'imap.example.com')
        ->set('port', 993)
        ->set('username', 'user@example.com')
        ->set('password', 'good-secret')
        ->set('encryption', 'ssl')
        ->set('isActive', true)
        ->call('testConnection')
        ->assertSet('testStatus', 'success')
        ->assertSee('Connection successful.')
        ->call('save')
        ->assertSet('testStatus', 'success')
        ->assertSee('IMAP settings saved.');

    $setting = $user->refresh()->imapSetting;

    expect($setting)->not->toBeNull()
        ->and($setting->hostname)->toBe('imap.example.com')
        ->and($setting->password)->not->toBe('good-secret')
        ->and($setting->decrypted_password)->toBe('good-secret')
        ->and($imapService->testConnectionAttempts)->toHaveCount(2);
});

test('settings error recovery flow lets a user fix credentials and save after a failed test', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $imapService = new ScriptedImapConnectionService(
        testConnectionHandler: function (string $hostname, int $port, string $username, string $password, ?string $encryption): bool {
            if ($password === 'bad-secret') {
                throw new ImapConnectionException('Invalid username or password. Please check your credentials.');
            }

            return true;
        },
    );

    app()->instance(ImapConnectionService::class, $imapService);

    Livewire::test(ImapSettingsComponent::class)
        ->set('hostname', 'imap.example.com')
        ->set('port', 993)
        ->set('username', 'user@example.com')
        ->set('password', 'bad-secret')
        ->set('encryption', 'ssl')
        ->call('testConnection')
        ->assertSet('testStatus', 'error')
        ->assertSee('Invalid username or password. Please check your credentials.')
        ->set('password', 'good-secret')
        ->call('save')
        ->assertSet('testStatus', 'success')
        ->assertSee('IMAP settings saved.');

    expect($user->refresh()->imapSetting?->decrypted_password)->toBe('good-secret')
        ->and($imapService->testConnectionAttempts)->toHaveCount(2);
});

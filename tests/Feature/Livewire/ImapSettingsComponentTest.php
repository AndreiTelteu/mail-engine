<?php

use App\Exceptions\Imap\ImapConnectionException;
use App\Livewire\ImapSettingsComponent;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\ImapConnectionService;
use Livewire\Livewire;
use Mockery;

afterEach(function () {
    Mockery::close();
});

function validImapSettings(array $overrides = []): array
{
    return array_merge([
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret-password',
        'encryption' => 'ssl',
        'isActive' => true,
    ], $overrides);
}

test('imap settings component loads existing settings', function () {
    $user = User::factory()->create();

    ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'mail.example.com',
        'port' => 143,
        'username' => 'existing@example.com',
        'password' => 'existing-secret',
        'encryption' => 'tls',
        'is_active' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(ImapSettingsComponent::class)
        ->assertSet('hostname', 'mail.example.com')
        ->assertSet('port', 143)
        ->assertSet('username', 'existing@example.com')
        ->assertSet('password', 'existing-secret')
        ->assertSet('encryption', 'tls')
        ->assertSet('isActive', false);
});

test('input validation prevents connection attempts for invalid imap settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $service = Mockery::mock(ImapConnectionService::class);
    $service->shouldNotReceive('testConnection');

    app()->instance(ImapConnectionService::class, $service);

    $invalidPayloads = [
        validImapSettings(['hostname' => '']),
        validImapSettings(['port' => 0]),
        validImapSettings(['port' => 70000]),
        validImapSettings(['username' => '']),
        validImapSettings(['password' => '']),
    ];

    foreach ($invalidPayloads as $payload) {
        $component = Livewire::test(ImapSettingsComponent::class)
            ->set('hostname', $payload['hostname'])
            ->set('port', $payload['port'])
            ->set('username', $payload['username'])
            ->set('password', $payload['password'])
            ->set('encryption', $payload['encryption'])
            ->set('isActive', $payload['isActive'])
            ->call('testConnection');

        if ($payload['hostname'] === '') {
            $component->assertHasErrors(['hostname']);
        }

        if ($payload['port'] < 1 || $payload['port'] > 65535) {
            $component->assertHasErrors(['port']);
        }

        if ($payload['username'] === '') {
            $component->assertHasErrors(['username']);
        }

        if ($payload['password'] === '') {
            $component->assertHasErrors(['password']);
        }
    }
});

test('connection can be tested successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $service = Mockery::mock(ImapConnectionService::class);
    $service->shouldReceive('testConnection')
        ->once()
        ->with('imap.example.com', 993, 'user@example.com', 'secret-password', 'ssl')
        ->andReturnTrue();

    app()->instance(ImapConnectionService::class, $service);

    Livewire::test(ImapSettingsComponent::class)
        ->set('hostname', 'imap.example.com')
        ->set('port', 993)
        ->set('username', 'user@example.com')
        ->set('password', 'secret-password')
        ->set('encryption', 'ssl')
        ->call('testConnection')
        ->assertHasNoErrors()
        ->assertSet('testStatus', 'success')
        ->assertSet('testMessage', 'Connection successful.')
        ->assertSee('Connection successful.');
});

test('connection errors are shown to the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $service = Mockery::mock(ImapConnectionService::class);
    $service->shouldReceive('testConnection')
        ->once()
        ->andThrow(new ImapConnectionException('Invalid username or password.'));

    app()->instance(ImapConnectionService::class, $service);

    Livewire::test(ImapSettingsComponent::class)
        ->set('hostname', 'imap.example.com')
        ->set('port', 993)
        ->set('username', 'user@example.com')
        ->set('password', 'secret-password')
        ->set('encryption', 'ssl')
        ->call('testConnection')
        ->assertHasErrors(['hostname'])
        ->assertSet('testStatus', 'error')
        ->assertSet('testMessage', 'Invalid username or password.')
        ->assertSee('Invalid username or password.');
});

test('imap settings can be saved after a successful connection test', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $service = Mockery::mock(ImapConnectionService::class);
    $service->shouldReceive('testConnection')
        ->once()
        ->with('imap.example.com', 993, 'user@example.com', 'secret-password', 'ssl')
        ->andReturnTrue();

    app()->instance(ImapConnectionService::class, $service);

    Livewire::test(ImapSettingsComponent::class)
        ->set('hostname', 'imap.example.com')
        ->set('port', 993)
        ->set('username', 'user@example.com')
        ->set('password', 'secret-password')
        ->set('encryption', 'ssl')
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('testStatus', 'success')
        ->assertSet('testMessage', 'IMAP settings saved.')
        ->assertDispatched('imap-settings-saved');

    $setting = $user->refresh()->imapSetting;

    expect($setting)->not->toBeNull();
    expect($setting->hostname)->toBe('imap.example.com');
    expect($setting->password)->not->toBe('secret-password');
    expect($setting->decrypted_password)->toBe('secret-password');
    expect($setting->is_active)->toBeTrue();
});

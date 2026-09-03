<?php

use App\Exceptions\Imap\ImapConnectionException;
use App\Jobs\SyncUserEmailsJob;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\ScriptedImapConnectionService;

function fakeImap(?callable $handler = null): ScriptedImapConnectionService
{
    $service = new ScriptedImapConnectionService(testConnectionHandler: $handler ?? fn (): bool => true);

    app()->instance(ImapConnectionService::class, $service);

    return $service;
}

/**
 * @return array{hostname: string, port: int, username: string, password: string, encryption: string, isActive: bool}
 */
function imapPayload(array $overrides = []): array
{
    return [
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'good-secret',
        'encryption' => 'ssl',
        'isActive' => true,
        ...$overrides,
    ];
}

test('the settings page renders the connection form and sync state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('mail.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MailSettings')
            ->where('setting', null)
            ->where('syncSession', null));
});

test('a user can save imap settings and the password is encrypted at rest', function () {
    $user = User::factory()->create();
    $imap = fakeImap();

    $this->actingAs($user)
        ->put(route('mail.settings.store'), imapPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $setting = $user->refresh()->imapSetting;

    expect($setting)->not->toBeNull()
        ->and($setting->hostname)->toBe('imap.example.com')
        ->and($setting->port)->toBe(993)
        ->and($setting->encryption)->toBe('ssl')
        ->and($setting->is_active)->toBeTrue()
        ->and($setting->getRawOriginal('password'))->not->toBe('good-secret')
        ->and($setting->decrypted_password)->toBe('good-secret')
        ->and($imap->testConnectionAttempts)->toHaveCount(1);
});

test('saving requires the connection details', function () {
    $user = User::factory()->create();
    fakeImap();

    $this->actingAs($user)
        ->from(route('mail.settings'))
        ->put(route('mail.settings.store'), ['hostname' => '', 'port' => 70000, 'username' => '', 'isActive' => 'maybe'])
        ->assertSessionHasErrors(['hostname', 'port', 'username', 'password', 'isActive']);

    expect($user->refresh()->imapSetting)->toBeNull();
});

test('a failed connection is reported under the connection key and nothing is saved', function () {
    $user = User::factory()->create();
    fakeImap(function (): bool {
        throw new ImapConnectionException('Invalid username or password. Please check your credentials.');
    });

    $this->actingAs($user)
        ->from(route('mail.settings'))
        ->put(route('mail.settings.store'), imapPayload(['password' => 'bad-secret']))
        ->assertSessionHasErrors(['connection' => 'Invalid username or password. Please check your credentials.']);

    expect($user->refresh()->imapSetting)->toBeNull();
});

test('testing a connection reports success without saving anything', function () {
    $user = User::factory()->create();
    $imap = fakeImap();

    $this->actingAs($user)
        ->post(route('mail.settings.test'), imapPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->refresh()->imapSetting)->toBeNull()
        ->and($imap->testConnectionAttempts)->toHaveCount(1);
});

test('an existing connection can be updated without re-entering the password', function () {
    $user = User::factory()->create();
    $imap = fakeImap();

    $this->actingAs($user)->put(route('mail.settings.store'), imapPayload());

    $this->actingAs($user)
        ->put(route('mail.settings.store'), imapPayload(['password' => '', 'isActive' => false]))
        ->assertSessionHasNoErrors();

    $setting = $user->refresh()->imapSetting;

    expect($setting->decrypted_password)->toBe('good-secret')
        ->and($setting->is_active)->toBeFalse()
        ->and($imap->testConnectionAttempts[1]['password'])->toBe('good-secret');
});

test('a synchronization cannot start before a mailbox is configured', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('mail.settings.sync'))
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
    expect(SyncSession::query()->count())->toBe(0);
});

test('a synchronization starts once and reports when one is already running', function () {
    Queue::fake();
    $user = User::factory()->create();
    fakeImap();

    $this->actingAs($user)->put(route('mail.settings.store'), imapPayload());

    $this->actingAs($user)
        ->post(route('mail.settings.sync'))
        ->assertSessionHas('success');

    Queue::assertPushed(SyncUserEmailsJob::class, 1);

    $this->actingAs($user)
        ->post(route('mail.settings.sync'))
        ->assertSessionHas('warning');

    Queue::assertPushed(SyncUserEmailsJob::class, 1);
    expect(SyncSession::query()->whereBelongsTo($user)->count())->toBe(1);
});

test('another user cannot see or change this mailbox connection', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    fakeImap();

    $this->actingAs($user)->put(route('mail.settings.store'), imapPayload());

    $this->actingAs($other)
        ->get(route('mail.settings'))
        ->assertInertia(fn ($page) => $page->where('setting', null));

    $this->actingAs($other)->put(route('mail.settings.store'), imapPayload([
        'hostname' => 'imap.other.test',
        'password' => 'other-secret',
    ]));

    expect($user->refresh()->imapSetting->hostname)->toBe('imap.example.com')
        ->and($other->refresh()->imapSetting->hostname)->toBe('imap.other.test');
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

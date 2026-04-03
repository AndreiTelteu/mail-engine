<?php

use App\Jobs\SyncUserEmailsJob;
use App\Livewire\SyncModalComponent;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

test('sync modal opens and shows no sessions message', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSee('No sync sessions found');
});

test('start sync creates a session and dispatches the job', function () {
    Bus::fake();

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('startSync')
        ->assertNotSet('syncSessionId', null);

    $session = SyncSession::query()
        ->where('user_id', $user->id)
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe('pending');

    Bus::assertDispatched(SyncUserEmailsJob::class, fn (SyncUserEmailsJob $job): bool => $job->userId === $user->id
        && $job->syncSessionId === $session->id);
});

test('start sync prevents duplicate active sessions', function () {
    Bus::fake();

    $user = User::factory()->create();

    SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('startSync');

    expect(SyncSession::where('user_id', $user->id)->count())->toBe(1);
    Bus::assertNotDispatched(SyncUserEmailsJob::class);
});

test('sync modal shows folder stats when available', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'folder_stats' => ['INBOX' => 150, 'Sent' => 30],
        'total_remote_count' => 180,
        'total_to_sync' => 10,
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('openModal')
        ->assertSee('INBOX')
        ->assertSee('150')
        ->assertSee('Sent')
        ->assertSee('30')
        ->assertSee('180');
});

test('sync modal shows progress bar during syncing', function () {
    $user = User::factory()->create();

    SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'folder_stats' => ['INBOX' => 10],
        'total_remote_count' => 10,
        'total_to_sync' => 5,
        'synced_count' => 3,
        'failed_count' => 1,
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('openModal')
        ->assertSee('Indexing Progress')
        ->assertSee('4 / 5')
        ->assertSee('3 synced')
        ->assertSee('1 failed');
});

test('sync modal shows log entries', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'folder_stats' => ['INBOX' => 10],
        'total_remote_count' => 10,
        'total_to_sync' => 2,
        'synced_count' => 1,
        'failed_count' => 1,
        'started_at' => now(),
    ]);

    SyncSessionLog::create([
        'sync_session_id' => $session->id,
        'to_address' => 'alice@example.com',
        'subject' => 'Hello World',
        'status' => 'success',
    ]);

    SyncSessionLog::create([
        'sync_session_id' => $session->id,
        'to_address' => 'bob@example.com',
        'subject' => 'Broken email',
        'status' => 'failed',
        'error_message' => 'Parse error',
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('openModal')
        ->assertSee('alice@example.com')
        ->assertSee('Hello World')
        ->assertSee('bob@example.com')
        ->assertSee('Broken email')
        ->assertSee('Parse error');
});

test('sync modal shows completed message when all emails already synced', function () {
    $user = User::factory()->create();

    SyncSession::create([
        'user_id' => $user->id,
        'status' => 'completed',
        'folder_stats' => ['INBOX' => 5],
        'total_remote_count' => 5,
        'total_to_sync' => 0,
        'completed_at' => now(),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->call('openModal')
        ->assertSee('All emails are already synced');
});

test('reset sync deletes the session, logs, and queued jobs', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'folder_stats' => ['INBOX' => 10],
        'total_remote_count' => 10,
        'total_to_sync' => 5,
        'synced_count' => 2,
        'started_at' => now(),
    ]);

    SyncSessionLog::create([
        'sync_session_id' => $session->id,
        'to_address' => 'test@example.com',
        'subject' => 'Test',
        'status' => 'success',
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->set('syncSessionId', $session->id)
        ->call('resetSync');

    expect(SyncSession::find($session->id))->toBeNull()
        ->and(SyncSessionLog::where('sync_session_id', $session->id)->count())->toBe(0);
});

test('reset sync does not affect other users sessions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherSession = SyncSession::create([
        'user_id' => $otherUser->id,
        'status' => 'syncing',
        'total_to_sync' => 5,
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->set('syncSessionId', $otherSession->id)
        ->call('resetSync');

    expect(SyncSession::find($otherSession->id))->not->toBeNull();
});

test('poll sync dispatches sync-completed event when session completes', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'completed',
        'folder_stats' => ['INBOX' => 5],
        'total_remote_count' => 5,
        'total_to_sync' => 3,
        'synced_count' => 3,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->set('syncSessionId', $session->id)
        ->set('showModal', true)
        ->call('pollSync')
        ->assertDispatched('sync-completed');
});

test('poll sync does not dispatch sync-completed twice', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'completed',
        'folder_stats' => ['INBOX' => 5],
        'total_remote_count' => 5,
        'total_to_sync' => 3,
        'synced_count' => 3,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->set('syncSessionId', $session->id)
        ->set('showModal', true)
        ->call('pollSync')
        ->assertDispatched('sync-completed')
        ->call('pollSync')
        ->assertNotDispatched('sync-completed');
});

test('poll sync does not fire when modal is closed', function () {
    $user = User::factory()->create();

    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'completed',
        'folder_stats' => ['INBOX' => 5],
        'total_remote_count' => 5,
        'total_to_sync' => 3,
        'synced_count' => 3,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(SyncModalComponent::class)
        ->set('syncSessionId', $session->id)
        ->set('showModal', false)
        ->call('pollSync')
        ->assertNotDispatched('sync-completed');
});

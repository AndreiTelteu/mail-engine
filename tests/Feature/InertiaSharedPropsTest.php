<?php

use App\Models\SyncSession;
use App\Models\User;

test('guests receive no user and no mailbox state', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('auth.user', null)
            ->where('mailbox', null)
            ->has('appName'));
});

test('the shell receives the authenticated user with initials', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.name', 'Ada Lovelace')
            ->where('auth.user.email', $user->email)
            ->where('auth.user.initials', 'AL')
            ->missing('auth.user.password'));
});

test('the shell receives mailbox connection and synchronization state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('mailbox.configured', false)
            ->where('mailbox.sync', null));

    $user->imapSetting()->create([
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'total_to_sync' => 10,
        'synced_count' => 4,
        'failed_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('mailbox.configured', true)
            ->where('mailbox.sync.status', 'syncing')
            ->where('mailbox.sync.isActive', true)
            ->where('mailbox.sync.syncedCount', 4)
            ->where('mailbox.sync.failedCount', 1)
            ->where('mailbox.sync.totalToSync', 10));
});

test('flash messages reach the client', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['warning' => 'Search is temporarily unavailable. Showing basic results.'])
        ->get(route('emails.index'))
        ->assertInertia(fn ($page) => $page
            ->where('flash.warning', 'Search is temporarily unavailable. Showing basic results.')
            ->where('flash.success', null)
            ->where('flash.error', null));
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

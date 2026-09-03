<?php

use App\Models\Email;
use App\Models\User;

function createDashboardEmail(User $user, string $folder, string $subject): Email
{
    return Email::withoutSyncingToSearch(fn (): Email => Email::create([
        'user_id' => $user->id,
        'message_id' => "dashboard-{$user->id}-{$folder}-{$subject}",
        'folder' => $folder,
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => $subject,
        'date' => now(),
        'body_text' => 'Body',
        'attachments' => [],
    ]));
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the overview reports the real state of the index and connection', function () {
    $user = User::factory()->create();
    createDashboardEmail($user, 'INBOX', 'First');
    createDashboardEmail($user, 'INBOX', 'Second');
    createDashboardEmail($user, 'Archive', 'Third');
    createDashboardEmail(User::factory()->create(), 'INBOX', 'Someone else');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('connection', null)
            ->where('index.messages', 3)
            ->where('index.folders', 2)
            ->where('index.failedCount', 0)
            ->whereNot('index.newestMessageAt', null)
            ->where('recentFolders.0.name', 'INBOX')
            ->where('recentFolders.0.count', 2));
});

test('the overview summarizes a saved connection without exposing the password', function () {
    $user = User::factory()->create();
    $user->imapSetting()->create([
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('connection.hostname', 'imap.example.com')
            ->where('connection.port', 993)
            ->where('connection.username', 'user@example.com')
            ->where('connection.encryption', 'ssl')
            ->where('connection.isActive', true)
            ->missing('connection.password'));
});

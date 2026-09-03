<?php

use App\Models\Email;
use App\Models\User;

function createRoutedEmail(User $user, array $overrides = []): Email
{
    static $counter = 0;

    $counter++;

    return Email::withoutSyncingToSearch(fn () => Email::create(array_merge([
        'user_id' => $user->id,
        'message_id' => "route-email-{$user->id}-{$counter}",
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['address' => $user->email, 'name' => $user->name]],
        'cc_addresses' => [],
        'subject' => 'Route detail email',
        'date' => now(),
        'body_text' => 'Route email body',
        'body_html' => '<p>Route email body</p>',
        'attachments' => [],
    ], $overrides)));
}

test('mail routes require authentication', function () {
    $email = Email::withoutSyncingToSearch(fn () => Email::create([
        'user_id' => User::factory()->create()->id,
        'message_id' => 'guest-route-email',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['address' => 'guest@example.com', 'name' => 'Guest']],
        'cc_addresses' => [],
        'subject' => 'Guest route email',
        'date' => now(),
        'body_text' => 'Guest route body',
        'body_html' => '<p>Guest route body</p>',
        'attachments' => [],
    ]));

    $this->get(route('mail.settings'))->assertRedirect(route('login'));
    $this->get(route('emails.index'))->assertRedirect(route('login'));
    $this->get(route('emails.show', ['emailId' => $email->id]))->assertRedirect(route('login'));
});

test('authenticated users can access the mail settings and search pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('mail.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MailSettings')
            ->where('setting', null));

    $this->get(route('emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->has('emails.data')
            ->has('folders')
            ->where('selectedEmail', null));
});

test('the mailbox lists folders with counts, attachment counts, and paging details', function () {
    $user = User::factory()->create();

    foreach (range(1, 21) as $index) {
        createRoutedEmail($user, [
            'folder' => $index > 18 ? 'Archive' : 'INBOX',
            'subject' => "Listed email {$index}",
            'attachments' => $index === 1
                ? [['filename' => 'invoice.pdf', 'filetype' => 'pdf']]
                : [],
        ]);
    }

    $this->actingAs($user)
        ->get(route('emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('indexedTotal', 21)
            ->where('folders', [
                ['name' => 'Archive', 'count' => 3],
                ['name' => 'INBOX', 'count' => 18],
            ])
            ->where('emails.total', 21)
            ->where('emails.currentPage', 1)
            ->where('emails.lastPage', 2)
            ->where('emails.from', 1)
            ->where('emails.to', 20)
            ->has('emails.data.0.attachmentCount'));

    $this->actingAs($user)
        ->get(route('emails.index', ['folder' => 'Archive', 'page' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.folder', 'Archive')
            ->where('emails.total', 3)
            ->where('emails.lastPage', 1));

    $this->actingAs($user)
        ->get(route('emails.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('emails.currentPage', 2));
});

test('search results carry escaped highlight markup', function () {
    $user = User::factory()->create();
    createRoutedEmail($user, ['subject' => 'Invoice <b>April</b>']);

    $this->actingAs($user)
        ->get(route('emails.index', ['query' => 'invoice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('emails.data.0.subject', fn (string $subject): bool => str_contains($subject, '<mark>Invoice</mark>')
                && ! str_contains($subject, '<b>')));
});

test('authenticated users can select one of their emails in the mailbox workspace', function () {
    $user = User::factory()->create();
    $email = createRoutedEmail($user);

    $this->actingAs($user)
        ->get(route('emails.index', ['email' => $email->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mailbox')
            ->where('filters.email', $email->id)
            ->where('selectedEmail.subject', 'Route detail email')
            ->where('selectedEmail.document', fn (string $document): bool => str_contains($document, 'Route email body')));
});

test('authenticated users can open their own email detail page', function () {
    $user = User::factory()->create();
    $email = createRoutedEmail($user);

    $this->actingAs($user)
        ->get(route('emails.show', ['emailId' => $email->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Email')
            ->where('email.subject', 'Route detail email')
            ->where('email.document', fn (string $document): bool => str_contains($document, 'Route email body')));
});

test('authenticated users cannot access another users email detail page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $email = createRoutedEmail($otherUser);

    $this->actingAs($user)
        ->get(route('emails.show', ['emailId' => $email->id]))
        ->assertNotFound();
});

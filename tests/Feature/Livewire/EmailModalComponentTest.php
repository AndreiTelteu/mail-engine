<?php

use App\Livewire\EmailModalComponent;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\User;
use App\Services\EmailHtmlDocumentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function createModalEmail(User $user, array $overrides = []): Email
{
    static $counter = 0;

    $counter++;
    $imapSetting = ImapSetting::query()->create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => $user->email,
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $mailFolder = MailFolder::query()->create([
        'imap_setting_id' => $imapSetting->id,
        'path' => 'INBOX',
        'uid_validity' => 1,
    ]);

    return Email::withoutSyncingToSearch(fn () => Email::create(array_merge([
        'mail_folder_id' => $mailFolder->id,
        'user_id' => $user->id,
        'uid_validity' => 1,
        'imap_uid' => $counter,
        'message_id' => "modal-message-{$user->id}-{$counter}",
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['address' => $user->email, 'name' => $user->name]],
        'cc_addresses' => [],
        'subject' => 'Modal email',
        'date' => now(),
        'body_text' => 'Fallback body',
        'body_html' => '<div><strong>Safe content</strong><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">link</a></div>',
        'body_current' => 'Safe content link',
        'body_quoted' => '',
        'preview' => 'Safe content link',
        'attachments' => [
            ['filename' => 'report.pdf', 'filetype' => 'application/pdf'],
            ['filename' => 'notes.txt', 'filetype' => 'text/plain'],
        ],
        'attachment_count' => 2,
        'content_hash' => hash('sha256', "modal-email-{$user->id}-{$counter}"),
        'parser_version' => 1,
    ], $overrides)));
}

test('email modal opens and renders the email inside a sandboxed iframe', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->assertSet('show', true)
        ->assertSet('iframeDocument', fn (string $document): bool => str_contains($document, '<base target="_blank">')
            && ! str_contains($document, '<script>alert(1)</script>')
            && ! str_contains($document, 'javascript:alert(2)')
            && ! str_contains($document, 'onclick='))
        ->assertSee('Modal email')
        ->assertSee('sender@example.com')
        ->assertSee('2 attachments')
        ->assertSee('report.pdf')
        ->assertSeeHtml('sandbox="allow-popups allow-popups-to-escape-sandbox"')
        ->assertSeeHtml('referrerpolicy="no-referrer"')
        ->assertSeeHtml('srcdoc="');
});

test('email modal hides the attachments section when the message has no attachments', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user, ['attachments' => []]);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->assertSet('show', true)
        ->assertDontSee('Attachments')
        ->assertDontSee('Metadata only');
});

test('email modal denies access to emails owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $email = createModalEmail($otherUser);

    $this->actingAs($user);

    expect(fn () => Livewire::test(EmailModalComponent::class)->call('open', $email->id))
        ->toThrow(ModelNotFoundException::class);
});

test('email modal can be closed', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->call('close')
        ->assertSet('show', false);
});

test('open in new tab dispatches an event', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->call('openInNewTab')
        ->assertDispatched('open-email-in-new-tab');
});

test('plain text emails are wrapped into an iframe document', function () {
    $document = app(EmailHtmlDocumentService::class)->build(null, "Line one\nLine two");

    expect($document)
        ->toContain('<base target="_blank">')
        ->toContain('<pre>')
        ->toContain('Line one')
        ->toContain('Line two');
});

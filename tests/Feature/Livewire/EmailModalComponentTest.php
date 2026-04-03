<?php

use App\Livewire\EmailModalComponent;
use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function createModalEmail(User $user, array $overrides = []): Email
{
    static $counter = 0;

    $counter++;

    return Email::withoutSyncingToSearch(fn () => Email::create(array_merge([
        'user_id' => $user->id,
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
        'attachments' => [
            ['filename' => 'report.pdf', 'filetype' => 'application/pdf'],
            ['filename' => 'notes.txt', 'filetype' => 'text/plain'],
        ],
    ], $overrides)));
}

test('email modal opens and renders the email inside a sandboxed iframe', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->assertSet('show', true)
        ->assertSet('iframeDocument', fn (string $document): bool => str_contains($document, '<script>alert(1)</script>')
            && str_contains($document, 'javascript:alert(2)'))
        ->assertSee('Modal email')
        ->assertSee('sender@example.com')
        ->assertSee('2 attachments')
        ->assertSee('report.pdf')
        ->assertSeeHtml('sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin"')
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
    $component = app(EmailModalComponent::class);
    $reflection = new ReflectionMethod($component, 'buildIframeDocument');
    $reflection->setAccessible(true);

    $email = createModalEmail(User::factory()->create(), [
        'body_html' => null,
        'body_text' => "Line one\nLine two",
    ]);

    $document = $reflection->invoke($component, $email);

    expect($document)
        ->toContain('<pre style=')
        ->toContain('Line one')
        ->toContain('Line two');
});

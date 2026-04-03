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

test('email modal opens and displays sanitized content', function () {
    $user = User::factory()->create();
    $email = createModalEmail($user);

    $this->actingAs($user);

    Livewire::test(EmailModalComponent::class)
        ->call('open', $email->id)
        ->assertSet('show', true)
        ->assertSee('Modal email')
        ->assertSee('sender@example.com')
        ->assertSee('report.pdf')
        ->assertDontSee('script')
        ->assertDontSee('javascript:');
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

test('html sanitization removes dangerous payloads while preserving safe markup', function () {
    $component = app(EmailModalComponent::class);

    $payloads = [
        '<div onclick="alert(1)">Hello</div>',
        '<script>alert(1)</script><p>Safe</p>',
        '<a href="javascript:alert(1)">Click</a>',
        '<img src="x" onerror="alert(1)"><span>Image</span>',
        '<iframe src="https://example.com"></iframe><strong>Keep me</strong>',
    ];

    foreach ($payloads as $payload) {
        $sanitized = $component->sanitizeHtml($payload);

        expect($sanitized)->not->toContain('onclick');
        expect($sanitized)->not->toContain('onerror');
        expect($sanitized)->not->toContain('javascript:');
        expect($sanitized)->not->toContain('<script');
        expect($sanitized)->not->toContain('<iframe');
    }

    expect($component->sanitizeHtml('<p><strong>Safe</strong> body</p>'))
        ->toContain('<strong>Safe</strong>');
});

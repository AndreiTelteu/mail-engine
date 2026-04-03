<?php

use App\Livewire\EmailSearchComponent;
use App\Models\Email;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    config(['scout.driver' => null]);
});

function createEmailForUser(User $user, array $overrides = []): Email
{
    static $counter = 0;

    $counter++;

    return Email::withoutSyncingToSearch(fn () => Email::create(array_merge([
        'user_id' => $user->id,
        'message_id' => "message-{$user->id}-{$counter}",
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender',
        'to_addresses' => [['address' => $user->email, 'name' => $user->name]],
        'cc_addresses' => [],
        'subject' => "Subject {$counter}",
        'date' => CarbonImmutable::now()->subMinutes($counter),
        'body_text' => "Body {$counter}",
        'body_html' => null,
        'attachments' => [],
    ], $overrides)));
}

test('search results update when the query changes', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createEmailForUser($user, [
        'subject' => 'Invoice for April',
        'body_text' => 'Quarterly invoice details',
    ]);
    createEmailForUser($user, [
        'subject' => 'Team lunch',
        'body_text' => 'Pick a time for lunch',
    ]);
    createEmailForUser($otherUser, [
        'subject' => 'Invoice for another user',
        'body_text' => 'Should stay hidden',
    ]);

    $this->actingAs($user);

    Livewire::test(EmailSearchComponent::class)
        ->set('query', 'invoice')
        ->assertSeeHtml('<mark>Invoice</mark>')
        ->assertDontSee('Team lunch')
        ->assertDontSee('Invoice for another user');
});

test('folder filter is applied to recent results', function () {
    $user = User::factory()->create();

    createEmailForUser($user, [
        'folder' => 'INBOX',
        'subject' => 'Inbox mail',
    ]);
    createEmailForUser($user, [
        'folder' => 'Sent',
        'subject' => 'Sent mail',
    ]);

    $this->actingAs($user);

    Livewire::test(EmailSearchComponent::class)
        ->set('folderFilter', 'Sent')
        ->assertSee('Sent mail')
        ->assertDontSee('Inbox mail');
});

test('search results are paginated', function () {
    $user = User::factory()->create();

    foreach (range(1, 11) as $index) {
        createEmailForUser($user, [
            'subject' => sprintf('PageItem-%02d', $index),
            'date' => CarbonImmutable::now()->subMinutes(12 - $index),
        ]);
    }

    $this->actingAs($user);

    Livewire::test(EmailSearchComponent::class)
        ->assertSee('PageItem-11')
        ->assertDontSee('PageItem-01')
        ->call('gotoPage', 2)
        ->assertSee('PageItem-01')
        ->assertDontSee('PageItem-11');
});

test('selecting and closing an email modal updates component state', function () {
    $user = User::factory()->create();
    $email = createEmailForUser($user);

    $this->actingAs($user);

    Livewire::test(EmailSearchComponent::class)
        ->call('selectEmail', $email->id)
        ->assertSet('selectedEmailId', $email->id)
        ->call('closeModal')
        ->assertSet('selectedEmailId', null);
});

test('search input uses debounce binding', function () {
    $user = User::factory()->create();
    createEmailForUser($user);

    $this->actingAs($user);

    Livewire::test(EmailSearchComponent::class)
        ->assertSeeHtml('wire:model.live.debounce.300ms="query"');
});

<?php

use App\DataTransferObjects\EmailData;
use App\Jobs\SyncEmailsJob;
use App\Livewire\EmailModalComponent;
use App\Livewire\EmailSearchComponent;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\DatabaseEmailIndexingService;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\Support\Fakes\ScriptedImapConnectionService;

beforeEach(function () {
    config(['scout.driver' => null]);
});

test('email synchronization flow indexes emails and renders them in the search interface', function () {
    $user = User::factory()->create();

    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'flow@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $imapService = new ScriptedImapConnectionService(mailboxes: [
        $setting->username => [
            'INBOX' => [
                '<invoice@example.com>' => new EmailData(
                    messageId: '<invoice@example.com>',
                    fromAddress: 'billing@example.com',
                    fromName: 'Billing',
                    toAddresses: [['address' => $user->email, 'name' => $user->name]],
                    ccAddresses: [],
                    subject: 'Quarterly invoice',
                    date: CarbonImmutable::parse('2026-04-03 10:00:00'),
                    bodyText: 'Invoice line items and payment details',
                    bodyHtml: '<p>Invoice line items and payment details</p>',
                    attachments: [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']],
                ),
            ],
            'Sent' => [
                '<followup@example.com>' => new EmailData(
                    messageId: '<followup@example.com>',
                    fromAddress: 'me@example.com',
                    fromName: 'Me',
                    toAddresses: [['address' => 'client@example.com', 'name' => 'Client']],
                    ccAddresses: [],
                    subject: 'Invoice follow up',
                    date: CarbonImmutable::parse('2026-04-03 11:00:00'),
                    bodyText: 'Following up on the invoice',
                    bodyHtml: null,
                    attachments: [],
                ),
            ],
        ],
    ]);

    app()->instance(ImapConnectionService::class, $imapService);
    app()->instance(EmailIndexingService::class, new DatabaseEmailIndexingService($imapService));

    (new SyncEmailsJob)->handle(
        app(ImapConnectionService::class),
        app(EmailIndexingService::class),
    );

    $user = $user->refresh();

    expect($user->emails()->count())->toBe(2)
        ->and($user->emails()->pluck('folder')->sort()->values()->all())->toBe(['INBOX', 'Sent']);

    $this->actingAs($user)
        ->get(route('emails.index'))
        ->assertOk()
        ->assertSee('Quarterly invoice')
        ->assertSee('Invoice follow up');
});

test('search flow lets a user find an indexed email and open its modal and detail page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $email = $user->emails()->create([
        'message_id' => '<search-flow@example.com>',
        'folder' => 'Archive',
        'from_address' => 'billing@example.com',
        'from_name' => 'Billing',
        'to_addresses' => [['address' => $user->email, 'name' => $user->name]],
        'cc_addresses' => [],
        'subject' => 'Project invoice',
        'date' => CarbonImmutable::parse('2026-04-03 12:00:00'),
        'body_text' => 'Invoice details for the archived project',
        'body_html' => '<p>Invoice details for the archived project</p>',
        'attachments' => [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']],
    ]);

    Livewire::test(EmailSearchComponent::class)
        ->set('query', 'invoice')
        ->assertSee('Project')
        ->assertSeeHtml('<mark>invoice</mark>')
        ->call('selectEmail', $email->id)
        ->assertSet('selectedEmailId', $email->id);

    Livewire::test(EmailModalComponent::class, ['emailId' => $email->id])
        ->assertSet('show', true)
        ->assertSee('Project invoice')
        ->assertSee('invoice.pdf');

    $this->get(route('emails.show', ['emailId' => $email->id]))
        ->assertOk()
        ->assertSee('Project invoice')
        ->assertSee('invoice.pdf');
});

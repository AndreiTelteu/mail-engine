<?php

use App\DataTransferObjects\ImapConnection;
use App\DataTransferObjects\MailFolderStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\User;
use App\Services\ImapConnectionService;
use Tests\Support\Fakes\ScriptedImapConnectionService;

function createRoutedEmail(User $user, array $overrides = []): Email
{
    static $counter = 0;

    $counter++;
    $folderPath = $overrides['folder'] ?? 'INBOX';
    $setting = ImapSetting::query()->firstOrCreate(
        ['user_id' => $user->id],
        [
            'hostname' => 'imap.example.com',
            'port' => 993,
            'username' => $user->email,
            'password' => 'secret',
            'encryption' => 'ssl',
            'is_active' => true,
        ],
    );
    $folder = MailFolder::query()->firstOrCreate(
        ['imap_setting_id' => $setting->id, 'path' => $folderPath],
        ['uid_validity' => 1],
    );

    return Email::withoutSyncingToSearch(fn () => Email::create(array_merge([
        'mail_folder_id' => $folder->id,
        'user_id' => $user->id,
        'uid_validity' => 1,
        'imap_uid' => $counter,
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
        'body_current' => 'Route email body',
        'body_quoted' => '',
        'preview' => 'Route email body',
        'attachments' => [],
        'content_hash' => hash('sha256', "route-{$user->id}-{$counter}"),
    ], $overrides)));
}

test('mail routes require authentication', function () {
    $email = createRoutedEmail(User::factory()->create(), [
        'message_id' => 'guest-route-email',
        'subject' => 'Guest route email',
        'body_text' => 'Guest route body',
        'body_html' => '<p>Guest route body</p>',
    ]);

    $this->get(route('mail.settings'))->assertRedirect(route('login'));
    $this->get(route('emails.index'))->assertRedirect(route('login'));
    $this->get(route('emails.show', ['emailId' => $email->id]))->assertRedirect(route('login'));
    $this->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 0]))->assertRedirect(route('login'));
    $this->get(route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 0]))->assertRedirect(route('login'));
});

test('attachments can be downloaded and safe files previewed from IMAP', function () {
    $user = User::factory()->create();
    $email = createRoutedEmail($user, [
        'attachments' => [
            ['filename' => null, 'filetype' => null],
            ['filename' => 'reports/invoice.pdf', 'filetype' => 'application/pdf'],
            ['filename' => 'logo.png', 'filetype' => 'image/png'],
        ],
    ]);
    $imap = new class extends ScriptedImapConnectionService
    {
        public array $requestedIndexes = [];

        public function getAttachment(ImapConnection $connection, string $folder, int $uid, int $index): ?string
        {
            $this->requestedIndexes[] = $index;

            return $index === 1
                ? "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
                : base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        }
    };
    $this->app->instance(ImapConnectionService::class, $imap);

    $this->actingAs($user)
        ->get(route('emails.show', ['emailId' => $email->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('email.attachments.0.downloadUrl', route('emails.attachments.download', ['emailId' => $email->id, 'index' => 1]))
            ->where('email.attachments.0.previewUrl', route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 1]))
            ->where('email.attachments.1.previewUrl', route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 2])));

    $this->get(route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeaderContains('Content-Disposition', 'inline;')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeaderContains('Content-Disposition', 'attachment;')
        ->assertHeaderContains('Content-Disposition', 'invoice.pdf')
        ->assertSee('%PDF-1.4');

    $this->get(route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 2]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeaderContains('Content-Disposition', 'inline;');

    expect($imap->requestedIndexes)->toBe([1, 1, 2])
        ->and($imap->disconnectCounts[$user->email] ?? null)->toBe(3);
});

test('attachment routes reject other users, stale mailboxes, missing parts and unsafe previews', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $email = createRoutedEmail($owner, [
        'attachments' => [['filename' => 'webpage.html', 'filetype' => 'text/html']],
    ]);
    $imap = new class extends ScriptedImapConnectionService
    {
        public int $uidValidity = 1;

        public ?string $content = '<script>alert(1)</script>';

        public function getFolderStatus(ImapConnection $connection, string $folder): MailFolderStatus
        {
            return new MailFolderStatus($folder, $this->uidValidity, 2, 1);
        }

        public function getAttachment(ImapConnection $connection, string $folder, int $uid, int $index): ?string
        {
            return $this->content;
        }
    };
    $this->app->instance(ImapConnectionService::class, $imap);

    $this->actingAs($otherUser)
        ->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 0]))
        ->assertNotFound();
    expect($imap->connectAttempts)->toBe([]);

    $this->actingAs($owner)
        ->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 2]))
        ->assertNotFound();
    expect($imap->connectAttempts)->toBe([]);

    $this->get(route('emails.show', ['emailId' => $email->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('email.attachments.0.previewUrl', null)
            ->where('email.attachments.0.downloadUrl', route('emails.attachments.download', ['emailId' => $email->id, 'index' => 0])));

    $this->get(route('emails.attachments.preview', ['emailId' => $email->id, 'index' => 0]))
        ->assertUnsupportedMediaType();

    $imap->content = null;
    $this->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 0]))
        ->assertNotFound();

    $imap->uidValidity = 2;
    $this->get(route('emails.attachments.download', ['emailId' => $email->id, 'index' => 0]))
        ->assertConflict();

    expect($imap->disconnectCounts[$owner->email] ?? null)->toBe(3);
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

test('email images include remote sources and resolve matching inline content from IMAP', function () {
    $user = User::factory()->create();
    $email = createRoutedEmail($user, [
        'body_html' => '<p><img src="https://images.example.test/banner.png"><img src="cid:logo%40example.test"></p>',
    ]);
    $imap = new class extends ScriptedImapConnectionService
    {
        public array $requestedContentIds = [];

        public function getInlineImages(ImapConnection $connection, string $folder, int $uid, array $contentIds): array
        {
            $this->requestedContentIds = $contentIds;

            return ['logo@example.test' => ['mimeType' => 'image/png', 'content' => 'image bytes']];
        }
    };
    $this->app->instance(ImapConnectionService::class, $imap);

    $response = $this->actingAs($user)
        ->get(route('emails.show', ['emailId' => $email->id]))
        ->assertOk();

    expect($imap->connectAttempts)->toBe([$user->email])
        ->and($imap->requestedContentIds)->toBe(['logo@example.test']);

    $response->assertInertia(fn ($page) => $page
        ->component('Email')
        ->where('email.document', function (string $document): bool {
            expect($document)->toContain('img-src data: https: http:')
                ->toContain('src="https://images.example.test/banner.png"')
                ->toContain('src="data:image/png;base64,'.base64_encode('image bytes').'"');

            return true;
        }));

    expect($imap->requestedContentIds)->toBe(['logo@example.test'])
        ->and($imap->disconnectCounts[$user->email] ?? null)->toBe(1);
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

test('the mailbox attachment filter keeps only matching emails and reports its state', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create();
    $matching = createRoutedEmail($user, [
        'subject' => 'Invoice with attachment',
        'attachments' => [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']],
        'attachment_count' => 1,
    ]);
    createRoutedEmail($user, ['subject' => 'Invoice without attachment']);
    createRoutedEmail(User::factory()->create(), [
        'subject' => 'Another private invoice',
        'attachments' => [['filename' => 'private.pdf', 'filetype' => 'application/pdf']],
        'attachment_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('emails.index', ['has_attachments' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.hasAttachments', true)
            ->where('emails.total', 1)
            ->where('emails.data.0.id', $matching->id));

    $this->actingAs($user)
        ->get(route('emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.hasAttachments', false)
            ->where('emails.total', 2));
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
            ->where('selectedEmail.document', fn (string $document): bool => str_contains($document, '<base target="_blank">')
                && str_contains($document, 'Route email body')));
});

test('email prefetch requests only resolve the selected message props', function () {
    $user = User::factory()->create();
    $email = createRoutedEmail($user);
    $assetVersion = app(HandleInertiaRequests::class)->version(request());

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $assetVersion,
            'X-Inertia-Partial-Component' => 'Mailbox',
            'X-Inertia-Partial-Data' => 'filters,selectedEmail',
        ])
        ->get(route('emails.index', ['email' => $email->id]))
        ->assertOk()
        ->assertJsonPath('component', 'Mailbox')
        ->assertJsonPath('props.filters.email', $email->id)
        ->assertJsonPath('props.selectedEmail.subject', 'Route detail email')
        ->assertJsonMissingPath('props.emails')
        ->assertJsonMissingPath('props.folders')
        ->assertJsonMissingPath('props.indexedTotal');
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

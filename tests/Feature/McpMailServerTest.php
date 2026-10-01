<?php

use App\DataTransferObjects\ImapConnection;
use App\DataTransferObjects\MailFolderStatus;
use App\Jobs\SyncUserEmailsJob;
use App\Mcp\Servers\MailServer;
use App\Mcp\Tools\GetEmailAttachmentTool;
use App\Mcp\Tools\GetEmailTool;
use App\Mcp\Tools\SearchEmailsTool;
use App\Mcp\Tools\SyncEmailsTool;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\McpAccessToken;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Queue;

function mcpEmail(User $user, string $folderPath, array $attributes = []): Email
{
    static $uid = 0;

    $uid++;
    $setting = ImapSetting::query()->firstOrCreate(
        ['user_id' => $user->id],
        [
            'hostname' => 'imap.example.test',
            'port' => 993,
            'username' => $user->email,
            'password' => 'secret',
            'encryption' => 'ssl',
            'is_active' => true,
        ],
    );
    $folder = MailFolder::query()->firstOrCreate(
        ['imap_setting_id' => $setting->id, 'path' => $folderPath],
        ['uid_validity' => 7],
    );

    return Email::withoutSyncingToSearch(fn (): Email => Email::query()->create(array_merge([
        'mail_folder_id' => $folder->id,
        'user_id' => $user->id,
        'uid_validity' => 7,
        'imap_uid' => $uid,
        'message_id' => "mcp-{$user->id}-{$uid}",
        'folder' => $folderPath,
        'from_address' => 'alice@example.test',
        'from_name' => 'Alice',
        'to_addresses' => [['address' => 'bob@example.test', 'name' => 'Bob']],
        'cc_addresses' => [['address' => 'carol@example.test', 'name' => 'Carol']],
        'subject' => 'Quarterly invoice',
        'date' => '2026-09-15 12:00:00',
        'body_text' => 'The complete message body',
        'body_html' => '<p>The complete message body</p>',
        'body_current' => 'The complete message body',
        'body_quoted' => '',
        'preview' => 'The complete message body',
        'attachments' => [],
        'attachment_count' => 0,
        'content_hash' => hash('sha256', "mcp-{$user->id}-{$uid}"),
    ], $attributes)));
}

test('search covers all folders and combines attachment, date, person, and text filters', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $match = mcpEmail($user, 'Archive/2026', [
        'attachments' => [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']],
        'attachment_count' => 1,
    ]);
    mcpEmail($user, 'INBOX', ['subject' => 'Unrelated update', 'date' => '2026-08-01 12:00:00']);
    mcpEmail($other, 'Archive/2026', ['subject' => 'Quarterly invoice private']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'query' => 'invoice',
        'has_attachment' => true,
        'date_from' => '2026-09-15',
        'date_to' => '2026-09-15',
        'person' => 'carol',
    ])->assertOk()->assertSee('"id":'.$match->id)->assertSee('"total":1');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'folder' => 'INBOX',
        'has_attachment' => false,
        'person' => 'bob@example.test',
    ])->assertOk()->assertSee('Unrelated update')->assertDontSee('Quarterly invoice');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'folder' => 'Archive/2026',
        'has_attachment' => false,
    ])->assertOk()->assertSee('"total":0');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'date_to' => '2026-08-01',
    ])->assertOk()->assertSee('Unrelated update')->assertDontSee('Quarterly invoice');
});

test('search paginates and rejects invalid filters', function () {
    $user = User::factory()->create();
    mcpEmail($user, 'INBOX', ['subject' => 'First']);
    mcpEmail($user, 'Sent', ['subject' => 'Second']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, ['per_page' => 1, 'page' => 2])
        ->assertOk()->assertSee('"total":2')->assertSee('"page":2')->assertSee('First');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'date_from' => '2026-09-16',
        'date_to' => '2026-09-15',
    ])->assertHasErrors();
});

test('search can return only email IDs and attachment metadata', function () {
    $user = User::factory()->create();
    $email = mcpEmail($user, 'Archive', [
        'subject' => 'Private subject',
        'attachments' => [['filename' => 'target-report.pdf', 'filetype' => 'application/pdf']],
        'attachment_count' => 1,
    ]);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'has_attachment' => true,
        'include_fields' => ['id', 'attachments'],
    ])->assertOk()
        ->assertSee('"id":'.$email->id)
        ->assertSee('"index":0')
        ->assertSee('target-report.pdf')
        ->assertDontSee(['Private subject', 'The complete message body', '"preview"', '"attachment_count"']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'include_fields' => ['id', 'unknown_field'],
    ])->assertHasErrors();

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'include_fields' => [],
    ])->assertHasErrors();
});

test('search lists recent mail without a text query using timestamp range ordering and limit', function () {
    $user = User::factory()->create();
    mcpEmail($user, 'INBOX', ['subject' => 'Yesterday', 'date' => '2026-09-30 20:00:00']);
    mcpEmail($user, 'INBOX', ['subject' => 'Today first', 'date' => '2026-10-01 08:00:00']);
    mcpEmail($user, 'Sent', ['subject' => 'Today second', 'date' => '2026-10-01 09:00:00']);
    mcpEmail($user, 'Archive', ['subject' => 'Today latest', 'date' => '2026-10-01 10:00:00']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'from' => '2026-10-01T00:00:00+03:00',
        'to' => '2026-10-02T00:00:00+03:00',
        'order_by' => 'date',
        'order_direction' => 'desc',
        'limit' => 2,
        'include_fields' => ['id', 'subject'],
    ])->assertOk()->assertSee(['Today latest', 'Today second', '"total":3', '"per_page":2'])
        ->assertDontSee(['Today first', 'Yesterday', 'The complete message body']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'from' => '2026-10-01T11:00:00+03:00',
        'to' => '2026-10-01T12:00:00+03:00',
        'include_fields' => ['subject'],
    ])->assertOk()->assertSee('Today first')->assertSee('"total":1');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'from' => '2026-10-01T08:00:00Z',
        'to' => '2026-10-01T09:00:00Z',
        'include_fields' => ['subject'],
    ])->assertOk()->assertSee('Today first')->assertSee('"total":1');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'from' => '2026-10-01T12:00:00+03:00',
        'to' => '2026-10-01T11:00:00+03:00',
    ])->assertHasErrors();
});

test('search retrieves chosen fields for a list of local email IDs or RFC message IDs', function () {
    $user = User::factory()->create();
    $first = mcpEmail($user, 'INBOX', ['subject' => 'First chosen']);
    $second = mcpEmail($user, 'Sent', ['subject' => 'Second chosen']);
    mcpEmail($user, 'Archive', ['subject' => 'Excluded']);

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'email_ids' => [$first->id, $second->id],
        'include_fields' => ['id', 'subject', 'body_text'],
        'order_by' => 'id',
        'order_direction' => 'asc',
    ])->assertOk()->assertSee(['First chosen', 'Second chosen', 'The complete message body', '"total":2'])
        ->assertDontSee('Excluded');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'message_ids' => [$first->message_id],
        'include_fields' => ['message_id'],
    ])->assertOk()->assertSee($first->message_id)->assertSee('"total":1');

    MailServer::actingAs($user)->tool(SearchEmailsTool::class, [
        'email_ids' => [$first->id, $first->id],
    ])->assertHasErrors();
});

test('sync tool starts one queued session and later returns new subjects only to its owner', function () {
    Queue::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $email = mcpEmail($user, 'INBOX', ['subject' => 'Just arrived']);

    MailServer::actingAs($user)->tool(SyncEmailsTool::class, [])
        ->assertOk()->assertSee('"status":"pending"');

    $session = SyncSession::query()->whereBelongsTo($user)->firstOrFail();
    Queue::assertPushed(SyncUserEmailsJob::class, 1);

    MailServer::actingAs($user)->tool(SyncEmailsTool::class, [])
        ->assertOk()->assertSee('"sync_session_id":'.$session->id);
    Queue::assertPushed(SyncUserEmailsJob::class, 1);

    SyncSessionLog::create([
        'sync_session_id' => $session->id,
        'email_id' => $email->id,
        'subject' => $email->subject,
        'status' => 'success',
    ]);
    $secondEmail = mcpEmail($user, 'INBOX', ['subject' => 'Another arrival']);
    SyncSessionLog::create([
        'sync_session_id' => $session->id,
        'email_id' => $secondEmail->id,
        'subject' => $secondEmail->subject,
        'status' => 'success',
    ]);
    $session->update(['status' => 'completed', 'completed_at' => now()]);

    MailServer::actingAs($user)->tool(SyncEmailsTool::class, ['sync_session_id' => $session->id, 'limit' => 1])
        ->assertOk()->assertSee(['Just arrived', '"id":'.$email->id, '"new_message_count":2', '"is_complete":true', '"has_more":true'])
        ->assertDontSee('Another arrival');

    MailServer::actingAs($user)->tool(SyncEmailsTool::class, ['sync_session_id' => $session->id, 'limit' => 1, 'page' => 2])
        ->assertOk()->assertSee('Another arrival')->assertDontSee('Just arrived');

    MailServer::actingAs($other)->tool(SyncEmailsTool::class, ['sync_session_id' => $session->id])
        ->assertHasErrors(['Sync session not found.']);
});

test('sync tool requires an active mailbox connection', function () {
    $user = User::factory()->create();

    MailServer::actingAs($user)->tool(SyncEmailsTool::class, [])
        ->assertHasErrors(['Configure an active IMAP connection before syncing.']);
});

test('email detail returns full content and only the authenticated users email', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $email = mcpEmail($owner, 'Sent', [
        'attachments' => [['filename' => 'report.txt', 'filetype' => 'text/plain']],
        'attachment_count' => 1,
    ]);

    MailServer::actingAs($owner)->tool(GetEmailTool::class, ['email_id' => $email->id])
        ->assertOk()->assertSee('The complete message body')->assertSee('report.txt')->assertSee('"index":0');

    MailServer::actingAs($other)->tool(GetEmailTool::class, ['email_id' => $email->id])
        ->assertHasErrors(['Email not found.']);
});

test('email detail returns only the requested fields', function () {
    $user = User::factory()->create();
    $email = mcpEmail($user, 'INBOX', ['subject' => 'Selected message']);

    MailServer::actingAs($user)->tool(GetEmailTool::class, [
        'email_id' => $email->id,
        'include_fields' => ['id', 'subject', 'body_text'],
    ])->assertOk()
        ->assertSee('Selected message')
        ->assertSee('The complete message body')
        ->assertDontSee(['"body_html"', '"attachments"', '"from_address"']);

    MailServer::actingAs($user)->tool(GetEmailTool::class, [
        'email_id' => $email->id,
        'include_fields' => ['subject', 'subject'],
    ])->assertHasErrors();
});

test('attachment content is fetched from the correct mailbox and disconnected', function () {
    $user = User::factory()->create();
    $email = mcpEmail($user, 'Archive/2026', [
        'attachments' => [['filename' => 'report.txt', 'filetype' => 'text/plain']],
        'attachment_count' => 1,
    ]);
    $resource = new class
    {
        public bool $disconnected = false;

        public function disconnect(): void
        {
            $this->disconnected = true;
        }
    };
    $connection = new ImapConnection($resource, $email->mailFolder->imapSetting);
    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->andReturn($connection);
    $imap->shouldReceive('getFolderStatus')->once()->with($connection, 'Archive/2026')
        ->andReturn(new MailFolderStatus('Archive/2026', 7, 100, 1));
    $imap->shouldReceive('getAttachment')->once()->with($connection, 'Archive/2026', $email->imap_uid, 0)
        ->andReturn('hello from IMAP');
    app()->instance(ImapConnectionService::class, $imap);

    MailServer::actingAs($user)->tool(GetEmailAttachmentTool::class, ['email_id' => $email->id, 'index' => 0])
        ->assertOk()->assertSee('report.txt')->assertSee(base64_encode('hello from IMAP'));

    expect($resource->disconnected)->toBeTrue();
});

test('attachment tool blocks cross-user access before connecting to IMAP', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $email = mcpEmail($owner, 'INBOX', [
        'attachments' => [['filename' => 'private.txt', 'filetype' => 'text/plain']],
        'attachment_count' => 1,
    ]);
    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldNotReceive('connect');
    app()->instance(ImapConnectionService::class, $imap);

    MailServer::actingAs($other)->tool(GetEmailAttachmentTool::class, ['email_id' => $email->id, 'index' => 0])
        ->assertHasErrors(['Attachment not found.']);
});

test('attachment tool refuses a stale mailbox UID', function () {
    $user = User::factory()->create();
    $email = mcpEmail($user, 'INBOX', [
        'attachments' => [['filename' => 'old.txt', 'filetype' => 'text/plain']],
        'attachment_count' => 1,
    ]);
    $resource = new class
    {
        public bool $disconnected = false;

        public function disconnect(): void
        {
            $this->disconnected = true;
        }
    };
    $connection = new ImapConnection($resource, $email->mailFolder->imapSetting);
    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->andReturn($connection);
    $imap->shouldReceive('getFolderStatus')->once()->andReturn(new MailFolderStatus('INBOX', 8, 100, 1));
    $imap->shouldNotReceive('getAttachment');
    app()->instance(ImapConnectionService::class, $imap);

    MailServer::actingAs($user)->tool(GetEmailAttachmentTool::class, ['email_id' => $email->id, 'index' => 0])
        ->assertHasErrors(['The mailbox has changed. Synchronize it before fetching attachments.']);

    expect($resource->disconnected)->toBeTrue();
});

test('the MCP HTTP endpoint requires authentication', function () {
    $this->postJson('/mcp/mail', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();

    $user = User::factory()->create();

    $this->withBasicAuth($user->email, 'password')->postJson('/mcp/mail', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

test('the MCP HTTP endpoint accepts authenticated requests', function () {
    $user = User::factory()->create();
    $token = McpAccessToken::factory()->for($user)->create();
    $url = route('mcp.mail', ['token' => $token->token_encrypted]);
    mcpEmail($user, 'INBOX', ['subject' => 'Authenticated MCP result']);

    $this->postJson($url, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()
        ->assertHeader('X-RateLimit-Limit', '3000')
        ->assertSee('search-emails')
        ->assertSee('include_fields')
        ->assertSee('sync-emails')
        ->assertSee('get-email-attachment');

    $this->postJson($url, [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'search-emails',
            'arguments' => ['folder' => 'INBOX'],
        ],
    ])->assertOk()
        ->assertSee('Authenticated MCP result');

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

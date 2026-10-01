<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetEmailAttachmentTool;
use App\Mcp\Tools\GetEmailTool;
use App\Mcp\Tools\SearchEmailsTool;
use App\Mcp\Tools\SyncEmailsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Mail Server')]
#[Version('1.0.0')]
#[Instructions('Search indexed mail or start a manual sync. Sync runs in the background: call sync-emails without an ID to start, then call it with the returned sync_session_id until complete to receive new message IDs and subjects. Search by email_ids and include_fields to read selected messages efficiently; fetch attachment content on demand.')]
class MailServer extends Server
{
    protected array $tools = [
        SearchEmailsTool::class,
        SyncEmailsTool::class,
        GetEmailTool::class,
        GetEmailAttachmentTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}

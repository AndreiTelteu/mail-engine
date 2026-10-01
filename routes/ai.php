<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\MailServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/mail', MailServer::class)
    ->middleware(['throttle:3000,1', AuthenticateMcpToken::class])
    ->name('mcp.mail');

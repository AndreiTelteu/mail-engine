<?php

namespace App\Http\Controllers;

use App\Models\McpAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class McpTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $plainToken = Str::random(64);

        $accessToken = $request->user()->mcpAccessTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'token_encrypted' => $plainToken,
        ]);

        return to_route('mail.settings')
            ->with('created_mcp_token_id', $accessToken->id)
            ->with('success', 'MCP token created. Copy its connection link from the dialog.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $accessToken = McpAccessToken::query()
            ->whereBelongsTo($request->user())
            ->findOrFail($tokenId);

        $accessToken->delete();

        return to_route('mail.settings')->with('success', 'MCP token deleted. Its connection link no longer works.');
    }
}

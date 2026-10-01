<?php

use App\Models\McpAccessToken;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('the owner can create a token and reopen its full MCP link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    McpAccessToken::factory()->for($other)->create();

    $this->actingAs($owner)
        ->post(route('mail.settings.mcp-tokens.store'))
        ->assertRedirect(route('mail.settings'));

    $token = $owner->mcpAccessTokens()->firstOrFail();
    expect($token->getRawOriginal('token_encrypted'))->not->toContain($token->token_encrypted);
    expect($token->token_hash)->toBe(hash('sha256', $token->token_encrypted));

    $this->actingAs($owner)->get(route('mail.settings'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('MailSettings')
            ->where('createdMcpTokenId', $token->id)
            ->has('mcpTokens', 1)
            ->where('mcpTokens.0.url', route('mcp.mail', ['token' => $token->token_encrypted])));

    $this->actingAs($owner)->get(route('mail.settings'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('mcpTokens', 1)
            ->where('mcpTokens.0.url', route('mcp.mail', ['token' => $token->token_encrypted])));
});

test('only the owner can delete a token and revocation blocks MCP requests', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = McpAccessToken::factory()->for($owner)->create();
    $url = route('mcp.mail', ['token' => $token->token_encrypted]);
    $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];

    $this->actingAs($other)->delete(route('mail.settings.mcp-tokens.destroy', $token->id))->assertNotFound();
    $this->postJson($url, $message)->assertOk();

    $this->actingAs($owner)->delete(route('mail.settings.mcp-tokens.destroy', $token->id))
        ->assertRedirect(route('mail.settings'));

    $this->postJson($url, $message)->assertUnauthorized();
    $this->assertDatabaseMissing('mcp_access_tokens', ['id' => $token->id]);
});

test('MCP token management requires a signed-in user', function () {
    $this->post(route('mail.settings.mcp-tokens.store'))->assertRedirect(route('login'));
    $this->delete(route('mail.settings.mcp-tokens.destroy', 1))->assertRedirect(route('login'));
});

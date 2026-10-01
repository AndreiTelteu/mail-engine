<?php

namespace App\Mcp\Tools;

use App\Jobs\SyncUserEmailsJob;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Description('Start a manual mailbox sync, or poll an existing sync using sync_session_id. Sync is queued, so new message IDs and subjects become available as folders finish. Call again until is_complete is true; page through new_messages if needed.')]
#[Name('sync-emails')]
class SyncEmailsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication required.');
        }

        $input = $request->validate([
            'sync_session_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($input['sync_session_id'])) {
            $session = SyncSession::query()
                ->whereBelongsTo($user)
                ->find($input['sync_session_id']);

            if (! $session instanceof SyncSession) {
                return Response::error('Sync session not found.');
            }
        } else {
            if (! ImapSetting::query()->whereBelongsTo($user)->where('is_active', true)->exists()) {
                return Response::error('Configure an active IMAP connection before syncing.');
            }

            $session = SyncSession::query()
                ->whereBelongsTo($user)
                ->whereIn('status', ['pending', 'counting', 'syncing'])
                ->latest()
                ->first();

            if (! $session instanceof SyncSession) {
                $session = SyncSession::create(['user_id' => $user->id, 'status' => 'pending']);
                SyncUserEmailsJob::dispatch($user->id, $session->id);
                $session->refresh();
            }
        }

        $newMessages = SyncSessionLog::query()
            ->whereBelongsTo($session)
            ->whereNotNull('email_id')
            ->orderBy('id')
            ->paginate($input['limit'] ?? 50, page: $input['page'] ?? 1);

        return Response::json([
            'sync_session_id' => $session->id,
            'status' => $session->status,
            'is_complete' => ! $session->isActive(),
            'synced_count' => $session->synced_count,
            'failed_count' => $session->failed_count,
            'new_message_count' => $newMessages->total(),
            'new_messages' => $newMessages->getCollection()->map(fn (SyncSessionLog $log): array => [
                'id' => $log->email_id,
                'subject' => $log->subject,
            ])->all(),
            'page' => $newMessages->currentPage(),
            'limit' => $newMessages->perPage(),
            'has_more' => $newMessages->hasMorePages(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sync_session_id' => $schema->integer()->description('Omit to start a manual sync or return an active sync. Pass the returned ID on later calls to check progress and get newly indexed subjects.'),
            'page' => $schema->integer()->description('Page of new message subjects, starting at 1.'),
            'limit' => $schema->integer()->description('Maximum new message subjects per page, from 1 to 100; defaults to 50.'),
        ];
    }
}

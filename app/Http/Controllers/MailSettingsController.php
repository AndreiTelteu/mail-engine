<?php

namespace App\Http\Controllers;

use App\Exceptions\Imap\ImapConnectionException;
use App\Http\Requests\StoreImapSettingsRequest;
use App\Jobs\SyncUserEmailsJob;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();
        $setting = $user->imapSetting;

        return Inertia::render('MailSettings', [
            'setting' => $setting instanceof ImapSetting ? [
                'hostname' => $setting->hostname,
                'port' => $setting->port,
                'username' => $setting->username,
                'encryption' => $setting->encryption,
                'isActive' => $setting->is_active,
            ] : null,
            'syncSession' => $this->syncSession($user),
        ]);
    }

    public function test(StoreImapSettingsRequest $request, ImapConnectionService $imap): RedirectResponse
    {
        $settings = $request->settings();

        try {
            $this->testConnection($imap, $settings);
        } catch (ImapConnectionException $exception) {
            throw ValidationException::withMessages(['connection' => $exception->getMessage()]);
        }

        return back()->with('success', "Connected to {$settings['hostname']} as {$settings['username']}.");
    }

    public function store(StoreImapSettingsRequest $request, ImapConnectionService $imap): RedirectResponse
    {
        $settings = $request->settings();

        try {
            $this->testConnection($imap, $settings);
        } catch (ImapConnectionException $exception) {
            throw ValidationException::withMessages(['connection' => $exception->getMessage()]);
        }

        /** @var User $user */
        $user = $request->user();
        $user->imapSetting()->updateOrCreate([], [
            'hostname' => $settings['hostname'],
            'port' => $settings['port'],
            'username' => $settings['username'],
            'password' => $settings['password'],
            'encryption' => $settings['encryption'],
            'is_active' => $settings['isActive'],
        ]);

        return back()->with('success', 'Connection saved. Background synchronization uses these settings.');
    }

    public function startSync(): RedirectResponse
    {
        /** @var User $user */
        $user = request()->user();

        if (! $user->imapSetting()->exists()) {
            return back()->with('error', 'Save your mailbox connection before starting a synchronization.');
        }

        if (SyncSession::query()->whereBelongsTo($user)->whereIn('status', ['pending', 'counting', 'syncing'])->exists()) {
            return back()->with('warning', 'A synchronization is already in progress.');
        }

        $session = SyncSession::create(['user_id' => $user->id, 'status' => 'pending']);
        SyncUserEmailsJob::dispatch($user->id, $session->id);

        return back()->with('success', 'Mailbox synchronization started.');
    }

    /**
     * @param  array{hostname: string, port: int, username: string, password: string, encryption: ?string, isActive: bool}  $settings
     */
    private function testConnection(ImapConnectionService $imap, array $settings): void
    {
        $imap->testConnection(
            $settings['hostname'],
            $settings['port'],
            $settings['username'],
            $settings['password'],
            $settings['encryption'],
        );
    }

    /**
     * @return array{status: string, isActive: bool, syncedCount: int, failedCount: int, totalToSync: int, remoteCount: int, folderStats: array<string, int>, startedAt: ?string, finishedAt: ?string, logs: array<int, array{id: int, status: string, address: string, subject: string, error: ?string}>}|null
     */
    private function syncSession(User $user): ?array
    {
        $session = SyncSession::query()->whereBelongsTo($user)->latest()->first();

        if (! $session instanceof SyncSession) {
            return null;
        }

        return [
            'status' => $session->status,
            'isActive' => $session->isActive(),
            'syncedCount' => $session->synced_count,
            'failedCount' => $session->failed_count,
            'totalToSync' => $session->total_to_sync,
            'remoteCount' => $session->total_remote_count,
            'folderStats' => $session->folder_stats ?? [],
            'startedAt' => $session->started_at?->diffForHumans(),
            'finishedAt' => $session->completed_at?->diffForHumans(),
            'logs' => $session->logs()
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (SyncSessionLog $log): array => [
                    'id' => $log->id,
                    'status' => $log->status,
                    'address' => $log->to_address ?: '(unknown recipient)',
                    'subject' => $log->subject ?: '(no subject)',
                    'error' => $log->error_message,
                ])
                ->all(),
        ];
    }
}

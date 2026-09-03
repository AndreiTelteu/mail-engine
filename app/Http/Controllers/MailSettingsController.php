<?php

namespace App\Http\Controllers;

use App\Exceptions\Imap\ImapConnectionException;
use App\Http\Requests\StoreImapSettingsRequest;
use App\Jobs\SyncUserEmailsJob;
use App\Models\ImapSetting;
use App\Models\SyncSession;
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
        $validated = $request->validated();

        try {
            $this->testConnection($imap, $validated);
        } catch (ImapConnectionException $exception) {
            throw ValidationException::withMessages(['hostname' => $exception->getMessage()]);
        }

        return back()->with('success', 'Connection successful.');
    }

    public function store(StoreImapSettingsRequest $request, ImapConnectionService $imap): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->testConnection($imap, $validated);
        } catch (ImapConnectionException $exception) {
            throw ValidationException::withMessages(['hostname' => $exception->getMessage()]);
        }

        /** @var User $user */
        $user = $request->user();
        $user->imapSetting()->updateOrCreate([], [
            'hostname' => $validated['hostname'],
            'port' => $validated['port'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'encryption' => $validated['encryption'],
            'is_active' => $validated['isActive'],
        ]);

        return back()->with('success', 'IMAP settings saved.');
    }

    public function startSync(): RedirectResponse
    {
        /** @var User $user */
        $user = request()->user();

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
     * @return array{status: string, isActive: bool}|null
     */
    private function syncSession(User $user): ?array
    {
        $session = SyncSession::query()->whereBelongsTo($user)->latest()->first();

        return $session instanceof SyncSession ? [
            'status' => $session->status,
            'isActive' => $session->isActive(),
        ] : null;
    }
}

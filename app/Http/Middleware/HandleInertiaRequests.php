<?php

namespace App\Http\Middleware;

use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $this->initials($user->name),
                ] : null,
            ],
            'mailbox' => $user instanceof User
                ? fn (): array => $this->mailboxStatus($user)
                : null,
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Connection and synchronization state the application shell always shows.
     *
     * @return array{configured: bool, sync: array{status: string, isActive: bool, syncedCount: int, failedCount: int, totalToSync: int, finishedAt: ?string}|null}
     */
    private function mailboxStatus(User $user): array
    {
        $session = SyncSession::query()
            ->whereBelongsTo($user)
            ->select([
                'id',
                'user_id',
                'status',
                'synced_count',
                'failed_count',
                'total_to_sync',
                'completed_at',
            ])
            ->latest()
            ->first();

        return [
            'configured' => $user->relationLoaded('imapSetting')
                ? $user->imapSetting instanceof ImapSetting
                : $user->imapSetting()->exists(),
            'sync' => $session instanceof SyncSession ? [
                'status' => $session->status,
                'isActive' => $session->isActive(),
                'syncedCount' => $session->synced_count,
                'failedCount' => $session->failed_count,
                'totalToSync' => $session->total_to_sync,
                'finishedAt' => $session->completed_at?->diffForHumans(),
            ] : null,
        ];
    }

    private function initials(string $name): string
    {
        return str($name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $part): string => str($part)->substr(0, 1)->upper()->toString())
            ->join('') ?: '?';
    }
}

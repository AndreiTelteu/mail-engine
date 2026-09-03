<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The launch surface: where the mailbox stands right now, and what to do next.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $emails = Email::query()->whereBelongsTo($user);

        $setting = $user->imapSetting;

        return Inertia::render('Dashboard', [
            'connection' => $setting instanceof ImapSetting ? [
                'hostname' => $setting->hostname,
                'port' => $setting->port,
                'username' => $setting->username,
                'encryption' => $setting->encryption,
                'isActive' => $setting->is_active,
            ] : null,
            'index' => [
                'messages' => (clone $emails)->count(),
                'folders' => (clone $emails)->distinct()->count('folder'),
                'newestMessageAt' => $this->formatTimestamp((clone $emails)->max('date')),
                'failedCount' => (clone $emails)->whereNotNull('indexing_failed_at')->count(),
            ],
            'recentFolders' => Email::query()
                ->whereBelongsTo($user)
                ->selectRaw('folder, count(*) as aggregate')
                ->groupBy('folder')
                ->orderByDesc('aggregate')
                ->limit(4)
                ->get()
                ->map(fn (Email $email): array => [
                    'name' => $email->folder,
                    'count' => (int) $email->getAttribute('aggregate'),
                ]),
        ]);
    }

    private function formatTimestamp(?string $timestamp): ?string
    {
        return $timestamp === null
            ? null
            : Date::parse($timestamp)->format('M j, Y g:i A');
    }
}

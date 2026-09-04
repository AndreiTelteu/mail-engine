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
        $index = Email::query()
            ->whereBelongsTo($user)
            ->selectRaw('COUNT(*) as messages')
            ->selectRaw('COUNT(DISTINCT folder) as folders')
            ->selectRaw('MAX(date) as newest_message_at')
            ->selectRaw('SUM(CASE WHEN indexing_failed_at IS NOT NULL THEN 1 ELSE 0 END) as failed_count')
            ->first();
        $setting = $user->imapSetting()->first();
        $user->setRelation('imapSetting', $setting);

        return Inertia::render('Dashboard', [
            'connection' => $setting instanceof ImapSetting ? [
                'hostname' => $setting->hostname,
                'port' => $setting->port,
                'username' => $setting->username,
                'encryption' => $setting->encryption,
                'isActive' => $setting->is_active,
            ] : null,
            'index' => [
                'messages' => (int) $index?->getAttribute('messages'),
                'folders' => (int) $index?->getAttribute('folders'),
                'newestMessageAt' => $this->formatTimestamp($index?->getAttribute('newest_message_at')),
                'failedCount' => (int) $index?->getAttribute('failed_count'),
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

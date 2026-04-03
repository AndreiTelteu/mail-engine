<div>
    <flux:button variant="primary" icon="arrow-path" wire:click="openModal">
        {{ __('Sync') }}
    </flux:button>

    <flux:modal wire:model="showModal" class="w-[56rem] max-w-[90vw]">
        <div wire:poll.500ms.visible="pollSync">
            <div class="space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Email Sync') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Synchronize emails from your IMAP server.') }}</flux:text>
                    </div>

                    @if ($this->syncSession)
                        @php
                            $session = $this->syncSession;
                        @endphp

                        <span @class([
                            'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium mr-9 -mt-1',
                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $session->status === 'pending',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $session->status === 'counting',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $session->status === 'syncing',
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $session->status === 'completed',
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $session->status === 'failed',
                        ])>
                            {{ str($session->status)->title() }}
                        </span>
                    @endif
                </div>

                @if ($this->syncSession)
                    @php
                        $session = $this->syncSession;
                    @endphp

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[16rem_1fr]">
                        {{-- Left pane: Folder stats --}}
                        <div class="min-w-0">
                            @if (filled($session->folder_stats))
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <div class="border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-800">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Remote Folders') }}</span>
                                            <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format($session->total_remote_count) }}</span>
                                        </div>
                                    </div>

                                    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @foreach ($session->folder_stats as $folder => $count)
                                            <div class="flex items-center justify-between px-3 py-2 text-xs">
                                                <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $folder }}</span>
                                                <span class="ml-2 shrink-0 tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($count) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="flex h-full items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-8 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Counting folders...') }}</span>
                                </div>
                            @endif

                            @if ($session->started_at)
                                <div class="mt-2 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Started :time', ['time' => $session->started_at->diffForHumans()]) }}
                                </div>
                            @endif
                        </div>

                        {{-- Right pane: Progress + Log --}}
                        <div class="min-w-0 space-y-4">
                            @if ($session->total_to_sync > 0)
                                @php
                                    $processed = $session->synced_count + $session->failed_count;
                                    $progress = $session->total_to_sync > 0 ? ($processed / $session->total_to_sync) : 0;
                                @endphp

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Indexing Progress') }}</span>
                                        <span class="tabular-nums text-zinc-900 dark:text-white">
                                            {{ number_format($processed) }} / {{ number_format($session->total_to_sync) }}
                                        </span>
                                    </div>

                                    <div class="h-3 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div
                                            class="h-full rounded-full bg-green-500 transition-all duration-300 dark:bg-green-400"
                                            style="width: {{ min(round($progress * 100, 1), 100) }}%"
                                        ></div>
                                    </div>

                                    <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span class="text-green-600 dark:text-green-400">{{ number_format($session->synced_count) }} {{ __('synced') }}</span>
                                        @if ($session->failed_count > 0)
                                            <span class="text-red-600 dark:text-red-400">{{ number_format($session->failed_count) }} {{ __('failed') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($session->status === 'completed' && $session->total_to_sync === 0)
                                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">
                                    {{ __('All emails are already synced.') }}
                                </div>
                            @elseif (in_array($session->status, ['pending', 'counting']))
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-400">
                                    {{ __('Scanning mailbox...') }}
                                </div>
                            @endif

                            @if ($this->logs->isNotEmpty())
                                <div class="space-y-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Sync Log') }}</span>

                                    <div
                                        class="h-56 w-full overflow-x-hidden overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-950 p-3 font-mono text-xs dark:border-zinc-800"
                                        x-data
                                        x-effect="$el.scrollTop = $el.scrollHeight"
                                    >
                                        <table class="w-full table-fixed">
                                            <colgroup>
                                                <col class="w-5">
                                                <col class="w-40">
                                                <col>
                                            </colgroup>
                                            <tbody>
                                                @foreach ($this->logs as $log)
                                                    <tr wire:key="log-{{ $log->id }}">
                                                        <td class="py-0.5 align-top">
                                                            @if ($log->status === 'success')
                                                                <span class="text-green-400">&#10003;</span>
                                                            @else
                                                                <span class="text-red-400">&#10007;</span>
                                                            @endif
                                                        </td>
                                                        <td class="truncate py-0.5 pr-2 text-zinc-500">{{ $log->to_address ?: __('(unknown)') }}</td>
                                                        <td class="truncate py-0.5 text-zinc-300">
                                                            {{ $log->subject ?: __('(no subject)') }}
                                                            @if ($log->status === 'failed' && $log->error_message)
                                                                <span class="text-red-400"> [{{ Str::limit($log->error_message, 40) }}]</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @elseif (in_array($session->status, ['syncing']))
                                <div class="space-y-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Sync Log') }}</span>
                                    <div class="flex h-56 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-950 dark:border-zinc-800">
                                        <span class="text-xs text-zinc-500">{{ __('Waiting for first results...') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-10 text-center dark:border-zinc-800 dark:bg-zinc-950/40">
                        <span class="text-zinc-500 dark:text-zinc-400">
                            {{ __('No sync sessions found. Start a sync to see progress here.') }}
                        </span>
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <div>
                        @if ($this->syncSession !== null && $this->syncSession->isActive())
                            <flux:button variant="danger" wire:click="resetSync" wire:confirm="{{ __('This will cancel the running sync and delete all progress. Continue?') }}">
                                {{ __('Reset') }}
                            </flux:button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Close') }}</flux:button>
                        </flux:modal.close>

                        @if ($this->syncSession === null || ! $this->syncSession->isActive())
                            <flux:button variant="primary" wire:click="startSync">
                                {{ __('Start Sync') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </flux:modal>
</div>

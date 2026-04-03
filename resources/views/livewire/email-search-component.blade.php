<section class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Mail search') }}</flux:heading>
            <flux:subheading>{{ __('Search across indexed mail, filter by folder, and open full messages without leaving the page.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice('{0} No results|{1} :count result|[2,*] :count results', $results->total(), ['count' => $results->total()]) }}
            </flux:text>
            <livewire:sync-modal-component />
        </div>
    </div>

    @if (session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
            {{ session('warning') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
        <div>
            <flux:input
                wire:model.live.debounce.300ms="query"
                type="search"
                :label="__('Search emails')"
                :placeholder="__('Search by sender, subject, or message content')"
            />
        </div>

        <div>
            <label for="folder-filter" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Folder') }}</label>
            <select
                id="folder-filter"
                wire:model.live="folderFilter"
                class="block w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
            >
                <option value="">{{ __('All folders') }}</option>
                @foreach ($folders as $folder)
                    <option value="{{ $folder }}">{{ $folder }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        @forelse ($results as $email)
            @php($result = $formattedResults[$email->id])
            @php($attachmentCount = collect($email->attachments ?? [])->filter(fn (mixed $attachment): bool => is_array($attachment) && (filled($attachment['filename'] ?? null) || filled($attachment['filetype'] ?? null)))->count())

            <button
                type="button"
                wire:key="email-result-{{ $email->id }}"
                wire:click="selectEmail({{ $email->id }})"
                class="flex w-full flex-col gap-2 border-b border-zinc-200 px-5 py-4 text-left transition hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:border-zinc-800 dark:hover:bg-zinc-800/60 dark:focus:bg-zinc-800/60"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{!! $result['from'] !!}</div>
                        <div class="text-base font-semibold text-zinc-900 dark:text-white">{!! $result['subject'] !!}</div>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $result['folder'] }}</span>
                        @if ($attachmentCount > 0)
                            <span class="inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-white px-2.5 py-1 font-medium dark:border-zinc-700 dark:bg-zinc-900">
                                <flux:icon.paper-clip class="size-3.5" />
                                <span>{{ trans_choice('{1} :count attachment|[2,*] :count attachments', $attachmentCount, ['count' => $attachmentCount]) }}</span>
                            </span>
                        @endif
                        <span>{{ $email->date?->format('M j, Y g:i A') }}</span>
                    </div>
                </div>

                <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{!! $result['preview'] !!}</p>
            </button>
        @empty
            <div class="px-5 py-12 text-center">
                <flux:heading size="lg">{{ __('No emails found') }}</flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ blank($query) ? __('Indexed emails will appear here once synchronization runs.') : __('Try a different search term or clear the folder filter.') }}
                </flux:text>
            </div>
        @endforelse
    </div>

    @if ($results->hasPages())
        <div class="pt-2">
            {{ $results->links() }}
        </div>
    @endif

    @if ($selectedEmailId !== null)
        <livewire:email-modal-component :email-id="$selectedEmailId" :key="'email-modal-'.$selectedEmailId" />
    @endif
</section>

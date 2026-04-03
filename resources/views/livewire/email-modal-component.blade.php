<section x-data @open-email-in-new-tab.window="window.open($event.detail.url, '_blank', 'noopener')">
    @if ($show && $email)
        @php
            $toAddresses = collect($email->to_addresses ?? [])
                ->map(function (array $address): string {
                    $emailAddress = $address['address'] ?? '';
                    $name = $address['name'] ?? null;

                    return filled($name) ? "{$name} <{$emailAddress}>" : $emailAddress;
                })
                ->filter()
                ->implode(', ');

            $ccAddresses = collect($email->cc_addresses ?? [])
                ->map(function (array $address): string {
                    $emailAddress = $address['address'] ?? '';
                    $name = $address['name'] ?? null;

                    return filled($name) ? "{$name} <{$emailAddress}>" : $emailAddress;
                })
                ->filter()
                ->implode(', ');
        @endphp

        @php
            $attachments = collect($email->attachments ?? [])
                ->filter(fn (mixed $attachment): bool => is_array($attachment)
                    && (filled($attachment['filename'] ?? null) || filled($attachment['filetype'] ?? null)))
                ->values();
        @endphp

        <div @class([
            'fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4 backdrop-blur-sm' => ! $standalone,
            'mx-auto w-full max-w-6xl p-4 sm:p-6' => $standalone,
        ])>
            @unless ($standalone)
                <div class="absolute inset-0" wire:click="close"></div>
            @endunless

            <article @class([
                'relative z-10 flex w-full flex-col overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900',
                'max-h-[90vh] max-w-5xl rounded-3xl shadow-2xl' => ! $standalone,
                'min-h-[70vh] rounded-3xl shadow-sm' => $standalone,
            ])>
                <div class="flex items-start justify-between gap-4 border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $email->folder }}</span>
                            <span>{{ $email->date?->format('M j, Y g:i A') }}</span>
                        </div>

                        <flux:heading size="lg">{{ $email->subject ?: __('(no subject)') }}</flux:heading>
                        <flux:text>{{ $email->from_name ? "{$email->from_name} <{$email->from_address}>" : $email->from_address }}</flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button variant="filled" wire:click="openInNewTab">{{ __('Open in new tab') }}</flux:button>
                        <flux:button variant="filled" wire:click="close">{{ __('Close') }}</flux:button>
                    </div>
                </div>

                <div class="overflow-y-auto px-6 py-5">
                    <div class="space-y-6">
                        <div class="grid gap-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div>
                                <div class="mb-1 font-medium text-zinc-900 dark:text-white">{{ __('From') }}</div>
                                <div class="text-zinc-600 dark:text-zinc-300">{{ $email->from_name ? "{$email->from_name} <{$email->from_address}>" : $email->from_address }}</div>
                            </div>

                            <div>
                                <div class="mb-1 font-medium text-zinc-900 dark:text-white">{{ __('To') }}</div>
                                <div class="text-zinc-600 dark:text-zinc-300">{{ $toAddresses !== '' ? $toAddresses : __('No recipients recorded') }}</div>
                            </div>

                            @if ($ccAddresses !== '')
                                <div>
                                    <div class="mb-1 font-medium text-zinc-900 dark:text-white">{{ __('Cc') }}</div>
                                    <div class="text-zinc-600 dark:text-zinc-300">{{ $ccAddresses }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-zinc-200 bg-white p-5 text-sm leading-7 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                            @if ($sanitizedBodyHtml !== '')
                                {!! $sanitizedBodyHtml !!}
                            @else
                                <div class="whitespace-pre-wrap">{{ $email->body_text }}</div>
                            @endif
                        </div>

                        @if ($attachments->isNotEmpty())
                            <section class="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <flux:icon.paper-clip class="size-4 text-zinc-500 dark:text-zinc-400" />
                                            <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                                        </div>
                                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('Metadata only — file contents are not stored.') }}</flux:text>
                                    </div>

                                    <span class="rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ trans_choice('{1} :count attachment|[2,*] :count attachments', $attachments->count(), ['count' => $attachments->count()]) }}
                                    </span>
                                </div>

                                <ul class="grid gap-3 md:grid-cols-2">
                                    @foreach ($attachments as $attachment)
                                        <li class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                                            <div class="mt-0.5 rounded-lg bg-zinc-100 p-2 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <flux:icon.paper-clip class="size-4" />
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $attachment['filename'] ?? __('unknown') }}</div>
                                                <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $attachment['filetype'] ?? __('unknown') }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    @elseif ($standalone)
        <div class="mx-auto max-w-3xl p-6">
            <div class="rounded-3xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Email not found') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This email could not be loaded or you do not have access to it.') }}</flux:text>
            </div>
        </div>
    @endif
</section>

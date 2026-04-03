<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('IMAP settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mail sync')" :subheading="__('Connect your inbox so new messages can be indexed and searched.')">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <flux:input
                        wire:model="hostname"
                        :label="__('Hostname')"
                        type="text"
                        placeholder="imap.example.com"
                        required
                    />
                    @error('hostname')
                        <flux:text color="red" class="mt-2">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="port"
                        :label="__('Port')"
                        type="number"
                        min="1"
                        max="65535"
                        required
                    />
                    @error('port')
                        <flux:text color="red" class="mt-2">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="username"
                        :label="__('Username')"
                        type="text"
                        autocomplete="username"
                        required
                    />
                    @error('username')
                        <flux:text color="red" class="mt-2">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <flux:input
                        wire:model="password"
                        :label="__('Password')"
                        type="password"
                        autocomplete="current-password"
                        viewable
                        required
                    />
                    @error('password')
                        <flux:text color="red" class="mt-2">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Encryption') }}</flux:heading>

                <flux:radio.group wire:model="encryption" variant="segmented">
                    <flux:radio value="ssl">{{ __('SSL') }}</flux:radio>
                    <flux:radio value="tls">{{ __('TLS') }}</flux:radio>
                    <flux:radio value="">{{ __('None') }}</flux:radio>
                </flux:radio.group>
                @error('encryption')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
            </div>

            <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700">
                <input wire:model="isActive" type="checkbox" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                <div>
                    <div class="font-medium text-zinc-900 dark:text-white">{{ __('Enable background sync') }}</div>
                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Allow scheduled jobs to fetch and index mail for this account.') }}</div>
                </div>
            </label>

            @if ($testMessage !== '')
                <div class="rounded-xl border px-4 py-3 {{ $testStatus === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' }}">
                    {{ $testMessage }}
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <flux:button type="button" variant="filled" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
                    <span wire:loading.remove wire:target="testConnection">{{ __('Test connection') }}</span>
                    <span wire:loading wire:target="testConnection">{{ __('Testing...') }}</span>
                </flux:button>

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save" data-test="save-imap-settings-button">
                    <span wire:loading.remove wire:target="save">{{ __('Save settings') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>

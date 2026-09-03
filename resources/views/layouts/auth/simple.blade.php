<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10">
            <div class="w-full max-w-[22rem]">
                <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-2 rounded-md text-md font-semibold tracking-[-0.01em] text-ink" wire:navigate>
                    <x-app-mark class="size-6" />
                    {{ config('app.name', 'Mail Engine') }}
                </a>
                <div class="panel p-6 shadow-overlay">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
                <p class="mt-5 text-sm text-ink-subtle">Self-hosted. Your mailbox data stays on this server.</p>
            </div>
        </div>
        @fluxScripts
    </body>
</html>

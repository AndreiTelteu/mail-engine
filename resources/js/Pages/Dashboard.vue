<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />
    <main class="grid min-h-screen place-items-center bg-zinc-950 p-6 text-zinc-100">
        <section class="w-full max-w-xl rounded-2xl border border-zinc-800 bg-zinc-900/70 p-8">
            <h1 class="text-2xl font-semibold">Mailbox workspace</h1>
            <p class="mt-3 text-zinc-400">Search indexed messages or manage the IMAP connection.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <Link href="/emails" class="rounded-lg bg-zinc-100 px-4 py-2 font-medium text-zinc-950">Search mail</Link>
                <Link href="/settings/mail" class="rounded-lg border border-zinc-700 px-4 py-2 hover:bg-zinc-800">Mail settings</Link>
            </div>
        </section>
    </main>
</template>

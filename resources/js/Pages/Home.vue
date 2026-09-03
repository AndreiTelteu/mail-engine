<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Mail Engine" />
    <main class="grid min-h-screen place-items-center bg-zinc-950 p-6 text-zinc-100">
        <section class="max-w-lg rounded-2xl border border-zinc-800 bg-zinc-900/70 p-8">
            <h1 class="text-2xl font-semibold">Mail Engine</h1>
            <p class="mt-3 text-zinc-400">A private, searchable workspace for your mailbox.</p>
            <Link href="/dashboard" class="mt-6 inline-block rounded-lg bg-zinc-100 px-4 py-2 font-medium text-zinc-950">Open dashboard</Link>
        </section>
    </main>
</template>

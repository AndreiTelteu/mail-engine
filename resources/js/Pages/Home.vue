<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Mail Engine" />
    <main class="min-h-screen bg-utility-paper text-utility-ink">
        <div class="utility-stripe h-3" aria-hidden="true" />
        <section class="utility-grid min-h-[calc(100vh-0.75rem)] p-4 sm:p-8">
            <div class="mx-auto flex min-h-[calc(100vh-4.75rem)] max-w-[100rem] flex-col justify-between border border-utility-ink bg-utility-paper p-6 sm:p-10">
                <div class="flex items-start justify-between gap-6"><p class="font-mono text-xs font-bold tracking-[0.12em]">MAIL ENGINE / PRIVATE MAIL RETRIEVAL</p><span class="h-3 w-3 bg-utility-signal" aria-label="System ready" /></div>
                <div class="max-w-5xl py-16"><h1 class="text-5xl font-black uppercase leading-[0.78] tracking-[-0.075em] sm:text-7xl lg:text-9xl">Your mail.<br>Under control.</h1><p class="mt-8 max-w-xl text-lg font-medium leading-7 text-utility-muted">A private workspace that finds the message you need without making your inbox somebody else’s product.</p><Link href="/dashboard" class="utility-button mt-10">Enter workspace <span aria-hidden="true">→</span></Link></div>
                <div class="flex flex-wrap justify-between gap-4 border-t border-utility-ink pt-4 font-mono text-[0.7rem] font-bold uppercase tracking-[0.1em]"><span>IMAP connected on your terms</span><span>Local search index</span><span>Untrusted HTML isolated</span></div>
            </div>
        </section>
    </main>
</template>

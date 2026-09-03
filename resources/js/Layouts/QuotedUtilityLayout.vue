<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ title: String, description: String });
const page = usePage();
const navigation = [
    { label: 'Mailbox', href: '/emails', match: '/emails' },
    { label: 'Settings', href: '/settings/mail', match: '/settings/mail' },
];
const isActive = (item) => page.url.startsWith(item.match);
</script>

<template>
    <main class="min-h-screen bg-utility-paper text-utility-ink">
        <div class="utility-stripe h-2" aria-hidden="true" />
        <header class="border-b border-utility-ink bg-utility-paper">
            <div class="mx-auto flex max-w-[100rem] items-stretch px-4 sm:px-6 lg:px-8">
                <Link href="/dashboard" class="flex min-h-20 items-center border-r border-utility-ink pr-6 text-xl font-black uppercase tracking-[-0.06em] sm:text-2xl">Mail<br>Engine</Link>
                <nav class="ml-auto flex" aria-label="Primary navigation">
                    <Link v-for="item in navigation" :key="item.href" :href="item.href" class="relative flex items-center border-l border-utility-ink px-4 text-xs font-extrabold uppercase tracking-[0.08em] transition-colors hover:bg-utility-ink hover:text-utility-paper sm:px-6" :class="{ 'bg-utility-ink text-utility-paper': isActive(item) }">
                        <span v-if="isActive(item)" class="absolute right-2 top-2 h-2 w-2 bg-utility-signal" aria-hidden="true" />
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
        </header>
        <div class="mx-auto grid max-w-[100rem] grid-cols-1 border-x border-utility-ink lg:grid-cols-[15rem_minmax(0,1fr)]">
            <aside class="border-b border-utility-ink bg-utility-ink p-5 text-utility-paper lg:min-h-[calc(100vh-5.5rem)] lg:border-b-0 lg:border-r">
                <p class="font-mono text-[0.7rem] font-bold tracking-[0.12em] text-utility-signal">PRIVATE / SELF-HOSTED</p>
                <p class="mt-4 max-w-[20ch] text-sm leading-5 text-utility-paper/75">Search, inspect, and act on your own mail.</p>
                <div class="mt-8 border-t border-utility-paper/30 pt-4"><p class="font-mono text-[0.65rem] uppercase tracking-[0.12em] text-utility-paper/50">Current station</p><p class="mt-2 text-sm font-bold uppercase tracking-wide">{{ title }}</p></div>
            </aside>
            <section class="min-w-0">
                <header class="utility-grid border-b border-utility-ink p-5 sm:p-8"><h1 class="max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-[-0.06em] sm:text-5xl">{{ title }}</h1><p v-if="description" class="mt-4 max-w-2xl text-sm font-medium leading-6 text-utility-muted">{{ description }}</p></header>
                <slot />
            </section>
        </div>
    </main>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    emails: Object,
    filters: Object,
    folders: Array,
});

const query = ref(props.filters.query);
const folder = ref(props.filters.folder ?? '');
let searchTimer;

function updateFilters() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/emails', { query: query.value || undefined, folder: folder.value || undefined }, {
            preserveState: true,
            replace: true,
        });
    }, 250);
}

watch([query, folder], updateFilters);
</script>

<template>
    <Head title="Mail search" />

    <main class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="border-b border-zinc-800 px-4 py-3 sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <Link href="/dashboard" class="font-semibold tracking-tight">Mail Engine</Link>
                <Link href="/settings/mail" class="text-sm text-zinc-400 hover:text-zinc-100">Mail settings</Link>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-6 p-4 sm:p-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Folders</p>
                <button
                    class="mb-1 block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-800"
                    :class="{ 'bg-zinc-800 text-white': !folder }"
                    type="button"
                    @click="folder = ''"
                >
                    All mail
                </button>
                <button
                    v-for="item in folders"
                    :key="item"
                    class="mb-1 block w-full truncate rounded-lg px-3 py-2 text-left text-sm text-zinc-300 hover:bg-zinc-800"
                    :class="{ 'bg-zinc-800 text-white': folder === item }"
                    type="button"
                    @click="folder = item"
                >
                    {{ item }}
                </button>
            </aside>

            <section class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70">
                <div class="border-b border-zinc-800 p-4">
                    <label class="sr-only" for="mail-search">Search mail</label>
                    <input
                        id="mail-search"
                        v-model="query"
                        class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-zinc-100 outline-none placeholder:text-zinc-500 focus:border-zinc-400 focus:ring-2 focus:ring-zinc-400/30"
                        placeholder="Search mail"
                        type="search"
                    >
                    <p class="mt-3 text-sm text-zinc-400">{{ emails.total }} messages</p>
                </div>

                <ul v-if="emails.data.length" class="divide-y divide-zinc-800">
                    <li v-for="email in emails.data" :key="email.id">
                        <Link :href="`/emails/${email.id}`" class="block px-4 py-4 hover:bg-zinc-800/70 focus:bg-zinc-800/70 focus:outline-none">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-100">{{ email.from }}</p>
                                    <p class="mt-1 truncate text-sm text-zinc-200">{{ email.subject }}</p>
                                    <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ email.preview }}</p>
                                </div>
                                <div class="shrink-0 text-right text-xs text-zinc-500">
                                    <p>{{ email.date }}</p>
                                    <p class="mt-2">{{ email.folder }}</p>
                                </div>
                            </div>
                        </Link>
                    </li>
                </ul>
                <p v-else class="p-8 text-center text-sm text-zinc-400">
                    {{ query || folder ? 'No messages match the current search.' : 'No indexed email yet.' }}
                </p>
            </section>
        </div>
    </main>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    emails: Object,
    filters: Object,
    folders: Array,
    selectedEmail: Object,
});

const query = ref(props.filters.query);
const folder = ref(props.filters.folder ?? '');
let searchTimer;

function navigate(emailId = props.filters.email) {
    router.get('/emails', {
        query: query.value || undefined,
        folder: folder.value || undefined,
        email: emailId || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function updateFilters() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => navigate(), 250);
}

watch([query, folder], updateFilters);
</script>

<template>
    <Head title="Mailbox" />
    <main class="min-h-screen bg-utility-paper text-utility-ink">
        <header class="flex h-16 items-stretch border-b border-utility-ink">
            <Link href="/dashboard" class="flex items-center border-r border-utility-ink px-4 text-lg font-black uppercase tracking-[-0.06em] sm:px-6">Mail<br>Engine</Link>
            <div class="flex min-w-0 flex-1 items-center justify-between gap-4 px-4 sm:px-6">
                <p class="truncate font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em]">Mailbox / private retrieval</p>
                <Link href="/settings/mail" class="shrink-0 border-b border-utility-ink pb-1 text-xs font-extrabold uppercase tracking-[0.08em] hover:text-utility-signal">Settings</Link>
            </div>
        </header>

        <div class="grid min-h-[calc(100vh-4rem)] lg:grid-cols-[14rem_22rem_minmax(0,1fr)]">
            <aside class="border-b border-utility-ink bg-utility-ink p-5 text-utility-paper lg:border-b-0 lg:border-r">
                <p class="font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em] text-utility-signal">Folders</p>
                <div class="mt-5 grid gap-1">
                    <button class="flex items-center justify-between border border-transparent px-3 py-2 text-left text-sm font-bold uppercase transition-colors hover:border-utility-paper" :class="{ 'border-utility-paper bg-utility-signal text-utility-ink': !folder }" type="button" @click="folder = ''"><span>All mail</span><span class="font-mono text-xs">/</span></button>
                    <button v-for="item in folders" :key="item" class="truncate border border-transparent px-3 py-2 text-left text-sm font-bold uppercase transition-colors hover:border-utility-paper" :class="{ 'border-utility-paper bg-utility-signal text-utility-ink': folder === item }" type="button" @click="folder = item">{{ item }}</button>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col border-b border-utility-ink lg:border-b-0 lg:border-r">
                <div class="border-b border-utility-ink p-4">
                    <label class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em]" for="mail-search">Find a message</label>
                    <input id="mail-search" v-model="query" class="utility-input mt-3" placeholder="Sender, subject, or content" type="search">
                    <p class="mt-3 text-xs font-bold uppercase tracking-[0.08em] text-utility-muted">{{ emails.total }} messages indexed</p>
                </div>
                <ul v-if="emails.data.length" class="min-h-0 overflow-y-auto">
                    <li v-for="email in emails.data" :key="email.id" class="border-b border-utility-ink last:border-b-0">
                        <button class="block w-full p-4 text-left transition-colors hover:bg-utility-ink hover:text-utility-paper" :class="{ 'bg-utility-ink text-utility-paper': filters.email === email.id }" type="button" @click="navigate(email.id)">
                            <div class="flex items-start justify-between gap-3"><p class="truncate text-xs font-extrabold uppercase tracking-wide">{{ email.from }}</p><time class="shrink-0 font-mono text-[0.65rem]">{{ email.date }}</time></div>
                            <p class="mt-2 truncate text-sm font-black leading-tight">{{ email.subject }}</p>
                            <p class="mt-2 line-clamp-2 text-xs leading-4 text-utility-muted">{{ email.preview }}</p>
                        </button>
                    </li>
                </ul>
                <div v-else class="utility-grid flex min-h-52 flex-1 items-center p-6 text-center"><p class="w-full text-sm font-black uppercase">{{ query || folder ? 'No matching record.' : 'No mail indexed yet.' }}</p></div>
            </section>

            <section class="min-w-0 bg-white">
                <article v-if="selectedEmail" class="flex min-h-full flex-col">
                    <header class="border-b border-utility-ink p-5 sm:p-7">
                        <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-utility-muted">{{ selectedEmail.folder }} / {{ selectedEmail.date }}</p><h1 class="mt-4 max-w-3xl text-2xl font-black uppercase leading-[0.9] tracking-[-0.05em] sm:text-4xl">{{ selectedEmail.subject }}</h1></div><a :href="`/emails/${selectedEmail.id}`" target="_blank" class="utility-button utility-button-secondary">New tab</a></div>
                        <dl class="mt-6 grid gap-3 border-t border-utility-line pt-4 text-sm"><div><dt class="font-mono text-[0.65rem] uppercase tracking-[0.12em] text-utility-muted">From</dt><dd class="mt-1 font-bold">{{ selectedEmail.from }}</dd></div><div><dt class="font-mono text-[0.65rem] uppercase tracking-[0.12em] text-utility-muted">To</dt><dd class="mt-1">{{ selectedEmail.to || 'No recipients recorded' }}</dd></div></dl>
                    </header>
                    <iframe class="min-h-[38rem] flex-1 bg-white" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" referrerpolicy="no-referrer" :srcdoc="selectedEmail.document" :title="selectedEmail.subject" />
                </article>
                <div v-else class="utility-grid grid min-h-full place-items-center p-8 text-center"><div><p class="text-3xl font-black uppercase leading-[0.9] tracking-[-0.05em]">Select a<br>message.</p><p class="mt-4 max-w-xs text-sm leading-5 text-utility-muted">The contents of the selected email appear here, isolated from the workspace.</p></div></div>
            </section>
        </div>
    </main>
</template>

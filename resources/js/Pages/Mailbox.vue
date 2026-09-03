<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import QuotedUtilityLayout from '../Layouts/QuotedUtilityLayout.vue';

const props = defineProps({ emails: Object, filters: Object, folders: Array });
const query = ref(props.filters.query);
const folder = ref(props.filters.folder ?? '');
let searchTimer;

function updateFilters() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => router.get('/emails', { query: query.value || undefined, folder: folder.value || undefined }, { preserveState: true, replace: true }), 250);
}

watch([query, folder], updateFilters);
</script>

<template>
    <Head title="Mailbox" />
    <QuotedUtilityLayout title="Mailbox" description="Search across your indexed messages, then move directly into the record you need.">
        <div class="grid lg:grid-cols-[13rem_minmax(0,1fr)]">
            <aside class="border-b border-utility-ink p-5 lg:border-b-0 lg:border-r">
                <p class="font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em] text-utility-muted">Folders</p>
                <div class="mt-4 grid gap-1">
                    <button class="flex items-center justify-between border border-transparent px-3 py-2 text-left text-sm font-bold uppercase transition-colors hover:border-utility-ink" :class="{ 'border-utility-ink bg-utility-signal': !folder }" type="button" @click="folder = ''"><span>All mail</span><span class="font-mono text-xs">/</span></button>
                    <button v-for="item in folders" :key="item" class="truncate border border-transparent px-3 py-2 text-left text-sm font-bold uppercase transition-colors hover:border-utility-ink" :class="{ 'border-utility-ink bg-utility-signal': folder === item }" type="button" @click="folder = item">{{ item }}</button>
                </div>
                <Link href="/settings/mail" class="mt-8 inline-flex border-b border-utility-ink pb-1 text-xs font-extrabold uppercase tracking-[0.08em] hover:text-utility-signal">Connection settings</Link>
            </aside>
            <section>
                <div class="border-b border-utility-ink p-5 sm:p-8">
                    <label class="font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em]" for="mail-search">Find a message</label>
                    <input id="mail-search" v-model="query" class="utility-input mt-3 text-base" placeholder="Sender, subject, or content" type="search">
                    <div class="mt-4 flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-[0.08em]"><span>{{ emails.total }} messages indexed</span><span class="font-mono text-utility-muted">{{ query || folder ? 'filtered record' : 'latest record' }}</span></div>
                </div>
                <ul v-if="emails.data.length">
                    <li v-for="email in emails.data" :key="email.id" class="border-b border-utility-ink last:border-b-0">
                        <Link :href="`/emails/${email.id}`" class="group grid gap-3 p-5 transition-colors hover:bg-utility-ink hover:text-utility-paper sm:grid-cols-[minmax(9rem,0.8fr)_minmax(0,2fr)_auto] sm:items-start sm:p-6">
                            <p class="truncate text-sm font-extrabold uppercase tracking-wide">{{ email.from }}</p>
                            <div class="min-w-0"><p class="truncate text-base font-black leading-tight">{{ email.subject }}</p><p class="mt-2 line-clamp-2 max-w-[70ch] text-sm leading-5 text-utility-muted group-hover:text-utility-paper/65">{{ email.preview }}</p></div>
                            <div class="flex gap-3 text-xs font-bold uppercase tracking-wide sm:block sm:text-right"><time>{{ email.date }}</time><span class="sm:mt-2 sm:block">{{ email.folder }}</span></div>
                        </Link>
                    </li>
                </ul>
                <div v-else class="utility-grid p-10 text-center"><p class="text-xl font-black uppercase">{{ query || folder ? 'No matching record.' : 'No mail indexed yet.' }}</p><p class="mt-3 text-sm text-utility-muted">{{ query || folder ? 'Change the search or folder and try again.' : 'Connect a mailbox to start building your local index.' }}</p></div>
            </section>
        </div>
    </QuotedUtilityLayout>
</template>

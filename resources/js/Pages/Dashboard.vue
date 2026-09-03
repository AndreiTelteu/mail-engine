<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import PageHeader from '../Components/PageHeader.vue';
import { routes } from '../routes';

const props = defineProps({
    connection: { type: Object, default: null },
    index: { type: Object, required: true },
    recentFolders: { type: Array, required: true },
});

const page = usePage();
const sync = computed(() => page.props.mailbox?.sync ?? null);

const syncSummary = computed(() => {
    if (! sync.value) {
        return 'Never run';
    }

    if (sync.value.isActive) {
        return `In progress \u2014 ${sync.value.syncedCount.toLocaleString()} of ${sync.value.totalToSync.toLocaleString()} indexed`;
    }

    if (sync.value.status === 'failed') {
        return `Failed ${sync.value.finishedAt ?? ''}`.trim();
    }

    return [sync.value.finishedAt, `${sync.value.syncedCount.toLocaleString()} indexed`]
        .filter(Boolean)
        .join(' \u00b7 ');
});

const encryptionLabel = computed(() => {
    if (! props.connection) {
        return null;
    }

    return { ssl: 'SSL', tls: 'TLS' }[props.connection.encryption] ?? 'None';
});
</script>

<template>
    <Head title="Overview" />

    <AppLayout title="Overview">
        <div class="min-h-0 flex-1 lg:overflow-y-auto">
            <PageHeader
                title="Overview"
                description="Where your mailbox stands, and what to do next."
            >
                <template #actions>
                    <Link
                        v-if="connection"
                        :href="routes.mailSettingsSync"
                        method="post"
                        as="button"
                        class="btn btn-secondary"
                        :aria-disabled="sync?.isActive ? 'true' : undefined"
                    >
                        <AppIcon name="sync" :size="14" />
                        {{ sync?.isActive ? 'Syncing\u2026' : 'Sync now' }}
                    </Link>
                    <Link :href="routes.mailbox" class="btn btn-primary">
                        <AppIcon name="search" :size="14" />
                        Search mail
                    </Link>
                </template>
            </PageHeader>

            <div class="grid gap-4 p-4 lg:grid-cols-2 lg:p-6">
                <section
                    v-if="! connection"
                    class="panel p-4 lg:col-span-2"
                    aria-labelledby="connect-heading"
                >
                    <h2 id="connect-heading" class="text-md font-semibold text-ink">Connect your mailbox</h2>
                    <p class="mt-1 max-w-[60ch] text-ink-muted">
                        Mail Engine reads messages over IMAP and keeps a private search index on this server.
                        Add your server details to start indexing.
                    </p>
                    <Link :href="routes.mailSettings" class="btn btn-primary mt-3">
                        <AppIcon name="plug" :size="14" />
                        Set up connection
                    </Link>
                </section>

                <section v-else class="panel" aria-labelledby="connection-heading">
                    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                        <h2 id="connection-heading" class="font-semibold text-ink">Mailbox connection</h2>
                        <Link :href="routes.mailSettings" class="btn btn-ghost min-h-7">Edit</Link>
                    </div>
                    <dl class="divide-y divide-line">
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Server</dt>
                            <dd class="tabular min-w-0 truncate text-right text-ink">
                                {{ connection.hostname }}:{{ connection.port }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Account</dt>
                            <dd class="min-w-0 truncate text-right text-ink">{{ connection.username }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Encryption</dt>
                            <dd class="text-ink">{{ encryptionLabel }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Background sync</dt>
                            <dd class="text-ink">
                                <span class="chip">
                                    <AppIcon
                                        :name="connection.isActive ? 'check' : 'close'"
                                        :size="12"
                                        :class="connection.isActive ? 'text-positive-text' : 'text-ink-subtle'"
                                    />
                                    {{ connection.isActive ? 'Every 15 minutes' : 'Paused' }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Last synchronization</dt>
                            <dd
                                class="min-w-0 truncate text-right"
                                :class="sync?.status === 'failed' ? 'text-critical-text' : 'text-ink'"
                            >
                                {{ syncSummary }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="panel" aria-labelledby="index-heading">
                    <div class="border-b border-line px-4 py-2.5">
                        <h2 id="index-heading" class="font-semibold text-ink">Search index</h2>
                    </div>
                    <dl class="divide-y divide-line">
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Indexed messages</dt>
                            <dd class="tabular text-ink">{{ index.messages.toLocaleString() }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Folders</dt>
                            <dd class="tabular text-ink">{{ index.folders.toLocaleString() }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Newest message</dt>
                            <dd class="tabular min-w-0 truncate text-right text-ink">
                                {{ index.newestMessageAt ?? 'None yet' }}
                            </dd>
                        </div>
                        <div v-if="index.failedCount" class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <dt class="text-ink-muted">Failed to index</dt>
                            <dd class="tabular text-critical-text">{{ index.failedCount.toLocaleString() }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="recentFolders.length" class="panel" aria-labelledby="folders-heading">
                    <div class="border-b border-line px-4 py-2.5">
                        <h2 id="folders-heading" class="font-semibold text-ink">Largest folders</h2>
                    </div>
                    <ul class="divide-y divide-line">
                        <li v-for="folder in recentFolders" :key="folder.name">
                            <Link
                                :href="`${routes.mailbox}?folder=${encodeURIComponent(folder.name)}`"
                                class="flex items-baseline justify-between gap-3 px-4 py-2.5 transition-colors duration-100 hover:bg-hover"
                            >
                                <span class="min-w-0 truncate text-ink">{{ folder.name }}</span>
                                <span class="tabular shrink-0 text-ink-subtle">{{ folder.count.toLocaleString() }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

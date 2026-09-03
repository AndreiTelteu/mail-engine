<script setup>
import { Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import Banner from '../Banner.vue';
import { routes } from '../../routes';

const props = defineProps({
    session: { type: Object, default: null },
    configured: { type: Boolean, default: false },
});

const isActive = computed(() => props.session?.isActive === true);
const processed = computed(() => (props.session?.syncedCount ?? 0) + (props.session?.failedCount ?? 0));
const percentage = computed(() => {
    const total = props.session?.totalToSync ?? 0;

    return total > 0 ? Math.min(Math.round((processed.value / total) * 100), 100) : 0;
});

const status = computed(
    () =>
        ({
            pending: { label: 'Queued', tone: 'text-accent-text' },
            counting: { label: 'Counting messages', tone: 'text-accent-text' },
            syncing: { label: 'Indexing', tone: 'text-accent-text' },
            completed: { label: 'Completed', tone: 'text-positive-text' },
            failed: { label: 'Failed', tone: 'text-critical-text' },
        })[props.session?.status] ?? { label: props.session?.status ?? 'Not run', tone: 'text-ink-muted' },
);

const folderStats = computed(() => Object.entries(props.session?.folderStats ?? {}));

const { start, stop } = usePoll(
    3000,
    { only: ['syncSession', 'mailbox'] },
    { autoStart: isActive.value, keepAlive: false },
);

watch(isActive, (active) => (active ? start() : stop()));
</script>

<template>
    <section class="panel" aria-labelledby="sync-heading">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5">
            <div>
                <h2 id="sync-heading" class="font-semibold text-ink">Synchronization</h2>
                <p class="text-sm" :class="status.tone" aria-live="polite">
                    {{ status.label }}
                    <span v-if="session?.finishedAt && ! isActive" class="text-ink-subtle">
                        &middot; {{ session.finishedAt }}
                    </span>
                    <span v-else-if="session?.startedAt && isActive" class="text-ink-subtle">
                        &middot; started {{ session.startedAt }}
                    </span>
                </p>
            </div>

            <Link
                v-if="! isActive"
                :href="routes.mailSettingsSync"
                method="post"
                as="button"
                class="btn btn-secondary"
                :aria-disabled="! configured ? 'true' : undefined"
            >
                <AppIcon name="sync" :size="14" />
                Sync now
            </Link>
        </div>

        <div class="grid gap-4 p-4">
            <Banner v-if="! configured" tone="info">
                Save a mailbox connection first. Synchronization reads from the server you configure above.
            </Banner>

            <p v-else-if="! session" class="text-ink-muted">
                No synchronization has run yet. Background sync checks your folders every 15 minutes, or start one now.
            </p>

            <template v-else>
                <div v-if="session.totalToSync > 0" class="grid gap-1.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-ink-muted">Indexing progress</p>
                        <p class="tabular text-ink">
                            {{ processed.toLocaleString() }} / {{ session.totalToSync.toLocaleString() }}
                        </p>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-active">
                        <div
                            class="h-full rounded-full bg-accent-solid transition-[width] duration-500 ease-out"
                            :style="{ width: `${percentage}%` }"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="tabular text-positive-text">{{ session.syncedCount.toLocaleString() }} indexed</span>
                        <span v-if="session.failedCount" class="tabular text-critical-text">
                            {{ session.failedCount.toLocaleString() }} failed
                        </span>
                        <span v-if="session.remoteCount" class="tabular text-ink-subtle">
                            {{ session.remoteCount.toLocaleString() }} on the server
                        </span>
                    </div>
                </div>

                <p v-else-if="session.status === 'completed'" class="text-ink-muted">
                    Every message on the server is already indexed.
                </p>

                <p v-else class="text-ink-muted">Scanning mailbox folders&hellip;</p>

                <div v-if="folderStats.length" class="grid gap-1.5">
                    <p class="text-ink-muted">Folders on the server</p>
                    <ul class="grid gap-px overflow-hidden rounded-md border border-line sm:grid-cols-2">
                        <li
                            v-for="[folder, count] in folderStats"
                            :key="folder"
                            class="flex items-baseline justify-between gap-2 bg-raised px-2.5 py-1.5"
                        >
                            <span class="min-w-0 truncate text-sm text-ink-muted" :title="folder">{{ folder }}</span>
                            <span class="tabular shrink-0 text-sm text-ink">{{ count.toLocaleString() }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="session.logs.length" class="grid gap-1.5">
                    <p class="text-ink-muted">Latest messages</p>
                    <ul class="grid divide-y divide-line overflow-hidden rounded-md border border-line">
                        <li v-for="log in session.logs" :key="log.id" class="flex items-start gap-2 bg-raised px-2.5 py-1.5">
                            <AppIcon
                                :name="log.status === 'success' ? 'check' : 'close'"
                                :size="13"
                                class="mt-0.5"
                                :class="log.status === 'success' ? 'text-positive-text' : 'text-critical-text'"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-ink" :title="log.subject">{{ log.subject }}</span>
                                <span class="block truncate text-sm text-ink-subtle">
                                    {{ log.error ?? log.address }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>
            </template>
        </div>
    </section>
</template>

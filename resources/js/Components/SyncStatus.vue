<script setup>
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { routes } from '../routes';

/**
 * Compact mailbox state for the application shell. While a synchronization is
 * running it refreshes only the shared `mailbox` prop, so the page the user is
 * working on is never replaced underneath them.
 */
const page = usePage();

const mailbox = computed(() => page.props.mailbox ?? { configured: false, sync: null });
const sync = computed(() => mailbox.value.sync);
const isActive = computed(() => sync.value?.isActive === true);

const processed = computed(() => (sync.value?.syncedCount ?? 0) + (sync.value?.failedCount ?? 0));
const percentage = computed(() => {
    const total = sync.value?.totalToSync ?? 0;

    return total > 0 ? Math.min(Math.round((processed.value / total) * 100), 100) : null;
});

const state = computed(() => {
    if (! mailbox.value.configured) {
        return { tone: 'caution', label: 'Mailbox not connected' };
    }

    if (! sync.value) {
        return { tone: 'neutral', label: 'Never synchronized' };
    }

    return {
        pending: { tone: 'accent', label: 'Sync queued' },
        counting: { tone: 'accent', label: 'Counting messages' },
        syncing: { tone: 'accent', label: 'Indexing messages' },
        completed: { tone: 'positive', label: 'Mailbox up to date' },
        failed: { tone: 'critical', label: 'Last sync failed' },
    }[sync.value.status] ?? { tone: 'neutral', label: sync.value.status };
});

const dotClass = {
    accent: 'bg-accent-solid',
    positive: 'bg-positive',
    caution: 'bg-caution',
    critical: 'bg-critical',
    neutral: 'bg-ink-subtle',
};

const { start, stop } = usePoll(4000, { only: ['mailbox'] }, { autoStart: isActive.value, keepAlive: false });

watch(isActive, (active) => (active ? start() : stop()));
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-2">
            <span class="grid size-4 shrink-0 place-items-center" aria-hidden="true">
                <span
                    class="size-1.5 rounded-full"
                    :class="[dotClass[state.tone], isActive ? 'animate-pulse' : '']"
                />
            </span>
            <p class="min-w-0 flex-1 truncate text-sm text-ink-muted">{{ state.label }}</p>
        </div>

        <div v-if="isActive && percentage !== null" class="grid gap-1.5 pl-6">
            <div class="h-1 overflow-hidden rounded-full bg-active">
                <div
                    class="h-full rounded-full bg-accent-solid transition-[width] duration-500 ease-out"
                    :style="{ width: `${percentage}%` }"
                />
            </div>
            <p class="tabular text-sm text-ink-subtle">
                {{ processed.toLocaleString() }} of {{ sync.totalToSync.toLocaleString() }} indexed
            </p>
        </div>

        <p v-else-if="! mailbox.configured" class="pl-6 text-sm text-ink-subtle">
            <Link :href="routes.mailSettings" class="text-accent-text underline decoration-accent-line underline-offset-2">
                Connect a mailbox
            </Link>
            to start indexing.
        </p>

        <p v-else-if="sync?.status === 'failed'" class="pl-6 text-sm text-ink-subtle">
            <Link :href="routes.mailSettings" class="text-accent-text underline decoration-accent-line underline-offset-2">
                Check the connection
            </Link>
        </p>

        <div v-else class="flex items-center justify-between gap-2 pl-6">
            <p v-if="sync?.finishedAt" class="truncate text-sm text-ink-subtle">{{ sync.finishedAt }}</p>
            <p v-else class="truncate text-sm text-ink-subtle">No messages indexed yet</p>
            <Link
                :href="routes.mailSettingsSync"
                method="post"
                as="button"
                class="btn btn-ghost -mr-1 min-h-6 px-1.5 text-sm"
            >
                <AppIcon name="sync" :size="13" />
                Sync
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '../AppIcon.vue';
import EmailListItem from './EmailListItem.vue';
import EmptyState from '../EmptyState.vue';
import { routes } from '../../routes';

const props = defineProps({
    emails: { type: Object, required: true },
    selectedId: { type: Number, default: null },
    filtered: { type: Boolean, default: false },
    mailboxConfigured: { type: Boolean, default: false },
    showFolder: { type: Boolean, default: true },
    searching: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'reset', 'page']);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <ul
            v-if="emails.data.length"
            class="min-h-0 flex-1 divide-y divide-line overflow-y-auto"
            :aria-busy="searching"
        >
            <li v-for="email in emails.data" :key="email.id">
                <EmailListItem
                    :email="email"
                    :selected="email.id === selectedId"
                    :show-folder="showFolder"
                    @select="emit('select', $event)"
                />
            </li>
        </ul>

        <div v-else class="min-h-0 flex-1 overflow-y-auto">
            <EmptyState
                v-if="filtered"
                icon="search"
                title="No messages match this search"
                description="Try fewer words, a different spelling, or search across all folders."
            >
                <template #actions>
                    <button type="button" class="btn btn-secondary" @click="emit('reset')">
                        Clear search and filters
                    </button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="! mailboxConfigured"
                icon="plug"
                title="Connect your mailbox"
                description="Add your IMAP server details and Mail Engine will index your messages for search."
            >
                <template #actions>
                    <Link :href="routes.mailSettings" class="btn btn-primary">Set up connection</Link>
                </template>
            </EmptyState>

            <EmptyState
                v-else
                icon="sync"
                title="No messages indexed yet"
                description="Synchronization runs in the background every 15 minutes. You can also start one now."
            >
                <template #actions>
                    <Link :href="routes.mailSettingsSync" method="post" as="button" class="btn btn-primary">
                        <AppIcon name="sync" :size="14" />
                        Sync now
                    </Link>
                    <Link :href="routes.mailSettings" class="btn btn-secondary">Review connection</Link>
                </template>
            </EmptyState>
        </div>

        <div
            v-if="emails.lastPage > 1"
            class="flex shrink-0 items-center justify-between gap-2 border-t border-line bg-surface px-3 py-2"
        >
            <p class="tabular text-sm text-ink-subtle">Page {{ emails.currentPage }} of {{ emails.lastPage }}</p>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="btn btn-secondary btn-icon"
                    :disabled="emails.currentPage <= 1"
                    @click="emit('page', emails.currentPage - 1)"
                >
                    <AppIcon name="chevronLeft" :size="15" />
                    <span class="sr-only">Previous page</span>
                </button>
                <button
                    type="button"
                    class="btn btn-secondary btn-icon"
                    :disabled="emails.currentPage >= emails.lastPage"
                    @click="emit('page', emails.currentPage + 1)"
                >
                    <AppIcon name="chevronRight" :size="15" />
                    <span class="sr-only">Next page</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import EmailList from '../Components/Mailbox/EmailList.vue';
import EmailReader from '../Components/Mailbox/EmailReader.vue';
import EmptyState from '../Components/EmptyState.vue';
import FolderList from '../Components/Mailbox/FolderList.vue';
import SearchToolbar from '../Components/Mailbox/SearchToolbar.vue';
import { useMailboxWorkspace } from '../composables/useMailboxWorkspace';

const props = defineProps({
    emails: { type: Object, required: true },
    filters: { type: Object, required: true },
    folders: { type: Array, required: true },
    indexedTotal: { type: Number, required: true },
    selectedEmail: { type: Object, default: null },
});

const page = usePage();
const mailboxConfigured = computed(() => page.props.mailbox?.configured === true);

const {
    query,
    searching,
    searchField,
    isFiltered,
    selectFolder,
    selectEmail,
    closeEmail,
    goToPage,
    reset,
    clearQuery,
} = useMailboxWorkspace(props);
</script>

<template>
    <Head title="Mail" />

    <AppLayout title="Mail">
        <template #sidebar>
            <FolderList
                :folders="folders"
                :active-folder="filters.folder ?? ''"
                :indexed-total="indexedTotal"
                @select="selectFolder"
            />
        </template>

        <div class="flex min-h-0 flex-1 lg:overflow-hidden">
            <!-- Result list: the full width on narrow screens, the left pane on wide ones. -->
            <section
                class="flex min-h-0 w-full flex-col border-line lg:w-[24rem] lg:shrink-0 lg:border-r"
                :class="selectedEmail ? 'hidden lg:flex' : 'flex'"
                aria-label="Messages"
            >
                <div ref="searchField">
                    <SearchToolbar
                        v-model="query"
                        :folder="filters.folder ?? ''"
                        :total="emails.total"
                        :from="emails.from"
                        :to="emails.to"
                        :searching="searching"
                        @clear-folder="selectFolder('')"
                        @clear-query="clearQuery"
                    />
                </div>

                <EmailList
                    :emails="emails"
                    :selected-id="filters.email"
                    :filtered="isFiltered"
                    :mailbox-configured="mailboxConfigured"
                    :show-folder="! filters.folder"
                    :searching="searching"
                    @select="selectEmail"
                    @reset="reset"
                    @page="goToPage"
                />

                <p class="hidden shrink-0 items-center gap-1.5 border-t border-line px-3 py-2 text-sm text-ink-subtle lg:flex">
                    <kbd class="kbd">/</kbd>
                    search
                    <kbd class="kbd ml-1.5">J</kbd>
                    <kbd class="kbd">K</kbd>
                    move
                    <kbd class="kbd ml-1.5">Esc</kbd>
                    close
                </p>
            </section>

            <!-- Reader: replaces the list on narrow screens, sits beside it on wide ones. -->
            <section
                class="min-h-0 min-w-0 flex-1 flex-col"
                :class="selectedEmail ? 'flex' : 'hidden lg:flex'"
                aria-label="Message"
            >
                <template v-if="selectedEmail">
                    <div class="flex shrink-0 items-center gap-1 border-b border-line bg-surface px-2 py-1.5 lg:hidden">
                        <button type="button" class="btn btn-ghost" @click="closeEmail">
                            <AppIcon name="chevronLeft" :size="15" />
                            All messages
                        </button>
                    </div>

                    <EmailReader :email="selectedEmail" @close="closeEmail" />
                </template>

                <div v-else class="grid min-h-0 flex-1 place-items-center">
                    <EmptyState
                        icon="mail"
                        title="No message selected"
                        description="Pick a message to read it here. Use J and K to move through results without leaving the keyboard."
                    />
                </div>
            </section>
        </div>
    </AppLayout>
</template>

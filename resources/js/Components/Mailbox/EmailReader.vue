<script setup>
import { computed } from 'vue';
import AppIcon from '../AppIcon.vue';
import AttachmentList from './AttachmentList.vue';
import { routes } from '../../routes';

const props = defineProps({
    email: { type: Object, required: true },
    /** `pane` sits beside the list; `page` is the standalone message URL. */
    variant: { type: String, default: 'pane' },
});

defineEmits(['close']);

const recipients = computed(() =>
    [
        { label: 'To', value: props.email.to || 'No recipients recorded' },
        ...(props.email.cc ? [{ label: 'Cc', value: props.email.cc }] : []),
    ],
);
</script>

<template>
    <article class="flex min-h-0 flex-1 flex-col bg-canvas">
        <header class="shrink-0 border-b border-line bg-surface px-4 py-3">
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-semibold tracking-[-0.01em] text-ink">{{ email.subject }}</h1>
                    <p class="mt-1 truncate text-ink-muted" :title="email.from">{{ email.from }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <a
                        :href="routes.email(email.id)"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-ghost btn-icon"
                        title="Open in a new tab"
                    >
                        <AppIcon name="externalLink" :size="15" />
                        <span class="sr-only">Open message in a new tab</span>
                    </a>
                    <button
                        v-if="variant === 'pane'"
                        type="button"
                        class="btn btn-ghost btn-icon"
                        title="Close message (Esc)"
                        @click="$emit('close')"
                    >
                        <AppIcon name="close" :size="15" />
                        <span class="sr-only">Close message</span>
                    </button>
                </div>
            </div>

            <dl class="mt-2.5 grid gap-1 border-t border-line pt-2.5 sm:grid-cols-[auto_minmax(0,1fr)] sm:gap-x-3">
                <template v-for="recipient in recipients" :key="recipient.label">
                    <dt class="text-sm text-ink-subtle">{{ recipient.label }}</dt>
                    <dd class="truncate text-sm text-ink-muted" :title="recipient.value">{{ recipient.value }}</dd>
                </template>
                <dt class="text-sm text-ink-subtle">Received</dt>
                <dd class="tabular text-sm text-ink-muted">
                    {{ email.date || 'No date recorded' }}
                    <span class="chip ml-1.5">{{ email.folder }}</span>
                </dd>
            </dl>
        </header>

        <div class="flex min-h-0 flex-1 flex-col">
            <!-- Sanitized mail HTML stays inside its sandboxed document, which owns its own scrolling. -->
            <iframe
                class="w-full flex-1 bg-white"
                :class="variant === 'page' ? 'min-h-[38rem]' : 'min-h-0'"
                sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin"
                referrerpolicy="no-referrer"
                :srcdoc="email.document"
                :title="`Message content: ${email.subject}`"
            />

            <div v-if="email.attachments.length" class="shrink-0 border-t border-line bg-surface px-4 py-3">
                <AttachmentList :attachments="email.attachments" />
            </div>
        </div>
    </article>
</template>

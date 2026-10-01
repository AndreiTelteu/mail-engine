<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    attachments: { type: Array, required: true },
});

const preview = ref(null);
let previewTimer;

const isImage = (attachment) => {
    const filetype = (attachment.filetype || '').toLowerCase();
    const extension = attachment.filename?.split('.').pop()?.toLowerCase();

    return ['image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp'].includes(filetype)
        || ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'].includes(extension);
};

const hidePreview = () => {
    clearTimeout(previewTimer);
    preview.value = null;
};

const showPreview = (attachment, event) => {
    hidePreview();

    if (! attachment.previewUrl) {
        return;
    }

    const bounds = event.currentTarget.getBoundingClientRect();
    const width = Math.min(420, window.innerWidth - 16);
    const height = Math.min(288, window.innerHeight - 16);
    const left = Math.max(8, Math.min(bounds.left, window.innerWidth - width - 8));
    const top = window.innerHeight - bounds.bottom >= height + 8
        ? bounds.bottom + 8
        : Math.max(8, bounds.top - height - 8);

    previewTimer = setTimeout(() => {
        preview.value = {
            ...attachment,
            image: isImage(attachment),
            style: { top: `${top}px`, left: `${left}px`, width: `${width}px` },
        };
    }, 250);
};

watch(() => props.attachments, hidePreview);

onMounted(() => {
    window.addEventListener('scroll', hidePreview, true);
    window.addEventListener('resize', hidePreview);
});

onBeforeUnmount(() => {
    hidePreview();
    window.removeEventListener('scroll', hidePreview, true);
    window.removeEventListener('resize', hidePreview);
});
</script>

<template>
    <section v-if="attachments.length" class="grid gap-2" aria-labelledby="attachment-heading">
        <h2 id="attachment-heading" class="font-medium text-ink">
            {{ attachments.length }} {{ attachments.length === 1 ? 'attachment' : 'attachments' }}
        </h2>

        <ul class="grid gap-1 sm:grid-cols-2">
            <li
                v-for="attachment in attachments"
                :key="attachment.downloadUrl"
                class="flex min-w-0 items-center gap-2 rounded-md border border-line bg-raised px-2.5 py-1.5 hover:bg-hover"
                @mouseenter="showPreview(attachment, $event)"
                @mouseleave="hidePreview"
            >
                <AppIcon name="paperclip" :size="14" class="text-ink-subtle" />
                <a
                    v-if="attachment.previewUrl"
                    :href="attachment.previewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-w-0 flex-1 truncate text-ink hover:text-accent-text"
                    :title="`Open ${attachment.filename}`"
                    @focus="showPreview(attachment, $event)"
                    @blur="hidePreview"
                >{{ attachment.filename }}</a>
                <span v-else class="min-w-0 flex-1 truncate text-ink" :title="attachment.filename">{{ attachment.filename }}</span>
                <a
                    :href="attachment.downloadUrl"
                    class="btn btn-ghost min-h-7 shrink-0"
                    :aria-label="`Download ${attachment.filename}`"
                >Download</a>
            </li>
        </ul>

        <Teleport to="body">
            <div
                v-if="preview"
                class="pointer-events-none fixed z-50 overflow-hidden rounded-lg border border-line-strong bg-surface shadow-overlay"
                :style="preview.style"
                aria-hidden="true"
            >
                <div class="truncate border-b border-line px-3 py-2 text-sm font-medium text-ink">{{ preview.filename }}</div>
                <img
                    v-if="preview.image"
                    :src="preview.previewUrl"
                    :alt="`Preview of ${preview.filename}`"
                    class="h-64 max-h-[50dvh] w-full bg-white object-contain"
                />
                <iframe
                    v-else
                    :src="preview.previewUrl"
                    :title="`Preview of ${preview.filename}`"
                    tabindex="-1"
                    class="h-64 max-h-[50dvh] w-full bg-white"
                />
            </div>
        </Teleport>
    </section>
</template>

<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
    attachments: { type: Array, required: true },
});
</script>

<template>
    <section v-if="attachments.length" class="grid gap-2" aria-labelledby="attachment-heading">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="attachment-heading" class="font-medium text-ink">
                {{ attachments.length }} {{ attachments.length === 1 ? 'attachment' : 'attachments' }}
            </h2>
            <p class="text-sm text-ink-subtle">Metadata only — file contents are not stored.</p>
        </div>

        <ul class="grid gap-1.5 sm:grid-cols-2">
            <li
                v-for="attachment in attachments"
                :key="`${attachment.filename}-${attachment.filetype}`"
                class="flex items-center gap-2 rounded-md border border-line bg-raised px-2.5 py-2"
            >
                <AppIcon name="paperclip" :size="14" class="text-ink-subtle" />
                <span class="min-w-0 flex-1 truncate text-ink" :title="attachment.filename">
                    {{ attachment.filename }}
                </span>
                <span class="chip shrink-0 uppercase">{{ attachment.filetype }}</span>
            </li>
        </ul>
    </section>
</template>

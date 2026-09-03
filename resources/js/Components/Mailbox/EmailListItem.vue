<script setup>
import { computed } from 'vue';
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    email: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    showFolder: { type: Boolean, default: true },
});

defineEmits(['select']);

/** Short, absolute time: today shows the clock, older messages show the date. */
const timestamp = computed(() => {
    if (! props.email.date) {
        return '';
    }

    const date = new Date(props.email.date);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    if (sameDay) {
        return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }

    return date.getFullYear() === now.getFullYear()
        ? date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
        : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
});

const fullTimestamp = computed(() =>
    props.email.date ? new Date(props.email.date).toLocaleString() : 'No date recorded',
);
</script>

<template>
    <button
        type="button"
        class="grid w-full gap-1 px-3 py-2.5 text-left transition-colors duration-100"
        :class="selected ? 'bg-accent-soft' : 'hover:bg-hover'"
        :aria-current="selected ? 'true' : undefined"
        @click="$emit('select', email.id)"
    >
        <span class="flex items-baseline gap-2">
            <!-- Result fields are escaped server-side, with matched terms in <mark>. -->
            <span class="min-w-0 flex-1 truncate font-medium text-ink" v-html="email.from" />
            <time :datetime="email.date" :title="fullTimestamp" class="shrink-0 text-sm text-ink-subtle">
                {{ timestamp }}
            </time>
        </span>

        <span class="truncate text-ink" v-html="email.subject" />

        <span class="truncate text-ink-subtle" v-html="email.preview" />

        <span v-if="(showFolder && email.folder) || email.attachmentCount" class="flex items-center gap-1.5 pt-0.5">
            <span v-if="showFolder && email.folder" class="chip max-w-[9rem]">
                <span class="truncate">{{ email.folder }}</span>
            </span>
            <span v-if="email.attachmentCount" class="chip">
                <AppIcon name="paperclip" :size="12" />
                {{ email.attachmentCount }}
                <span class="sr-only">{{ email.attachmentCount === 1 ? 'attachment' : 'attachments' }}</span>
            </span>
        </span>
    </button>
</template>

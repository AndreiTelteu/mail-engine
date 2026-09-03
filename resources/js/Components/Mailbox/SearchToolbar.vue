<script setup>
import { computed } from 'vue';
import AppIcon from '../AppIcon.vue';
import Spinner from '../Spinner.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    folder: { type: String, default: '' },
    total: { type: Number, default: 0 },
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    searching: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'clear-folder', 'clear-query']);

const summary = computed(() => {
    if (props.total === 0) {
        return props.modelValue || props.folder ? 'No matching messages' : 'No messages indexed';
    }

    const range = props.from && props.to && props.total > props.to ? `${props.from}\u2013${props.to} of ` : '';
    const noun = props.total === 1 ? 'message' : 'messages';

    return props.modelValue
        ? `${range}${props.total.toLocaleString()} ${noun} matching`
        : `${range}${props.total.toLocaleString()} ${noun}`;
});
</script>

<template>
    <div class="grid gap-2 border-b border-line bg-surface px-3 py-2.5">
        <div class="relative">
            <span class="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-ink-subtle">
                <AppIcon name="search" :size="15" />
            </span>
            <label for="mailbox-search" class="sr-only">Search messages by sender, subject, or content</label>
            <input
                id="mailbox-search"
                :value="modelValue"
                type="search"
                class="field-input min-h-8 pr-16 pl-7"
                placeholder="Search mail"
                autocomplete="off"
                spellcheck="false"
                @input="emit('update:modelValue', $event.target.value)"
            />
            <span class="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                <Spinner v-if="searching" class="text-ink-subtle" />
                <button
                    v-else-if="modelValue"
                    type="button"
                    class="btn btn-ghost btn-icon min-h-6 min-w-6"
                    @click="emit('clear-query')"
                >
                    <AppIcon name="close" :size="13" />
                    <span class="sr-only">Clear search</span>
                </button>
                <kbd v-else class="kbd" aria-hidden="true">/</kbd>
            </span>
        </div>

        <div class="flex min-h-5 items-center gap-2">
            <p class="tabular min-w-0 flex-1 truncate text-sm text-ink-subtle" aria-live="polite">
                {{ summary }}
            </p>
            <button
                v-if="folder"
                type="button"
                class="chip max-w-[10rem] transition-colors duration-100 hover:text-ink"
                @click="emit('clear-folder')"
            >
                <AppIcon name="folder" :size="12" />
                <span class="truncate">{{ folder }}</span>
                <AppIcon name="close" :size="12" />
                <span class="sr-only">Clear folder filter</span>
            </button>
        </div>
    </div>
</template>

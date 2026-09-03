<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="grid place-items-center px-6 py-12 text-center">
        <div class="grid max-w-[26rem] justify-items-center gap-3">
            <span class="grid size-9 place-items-center rounded-full bg-active text-ink-subtle">
                <AppIcon :name="icon" :size="18" />
            </span>
            <div class="grid gap-1">
                <p class="text-md font-semibold text-ink">{{ title }}</p>
                <p v-if="description" class="text-ink-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="mt-1 flex flex-wrap items-center justify-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line bg-surface px-4 py-3 lg:px-6">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold tracking-[-0.01em] text-ink">{{ title }}</h1>
            <p v-if="description" class="mt-0.5 max-w-[60ch] text-ink-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>

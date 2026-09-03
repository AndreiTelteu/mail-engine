<script setup>
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: null },
    error: { type: String, default: null },
});
</script>

<template>
    <div class="grid gap-1.5">
        <label :for="id" class="field-label">{{ label }}</label>
        <slot :described-by="[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined" />
        <p v-if="error" :id="`${id}-error`" class="field-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="field-hint">{{ hint }}</p>
    </div>
</template>

<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: null },
});

const tones = {
    info: { icon: 'info', iconClass: 'text-accent-text', border: 'border-accent-line' },
    success: { icon: 'checkCircle', iconClass: 'text-positive-text', border: 'border-positive' },
    warning: { icon: 'alertTriangle', iconClass: 'text-caution-text', border: 'border-caution' },
    error: { icon: 'alertCircle', iconClass: 'text-critical-text', border: 'border-critical' },
};
</script>

<template>
    <div
        class="flex items-start gap-2 rounded-lg border bg-raised px-3 py-2.5"
        :class="tones[tone].border"
        :role="tone === 'error' ? 'alert' : 'status'"
    >
        <AppIcon :name="tones[tone].icon" class="mt-px" :class="tones[tone].iconClass" />
        <div class="min-w-0 flex-1">
            <p v-if="title" class="font-medium text-ink">{{ title }}</p>
            <p class="text-ink-muted"><slot /></p>
        </div>
    </div>
</template>

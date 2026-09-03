<script setup>
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const page = usePage();

const tones = {
    success: { icon: 'checkCircle', iconClass: 'text-positive-text' },
    warning: { icon: 'alertTriangle', iconClass: 'text-caution-text' },
    error: { icon: 'alertCircle', iconClass: 'text-critical-text' },
};

const messages = ref([]);
const timers = new Map();
let nextId = 0;

const dismiss = (id) => {
    messages.value = messages.value.filter((message) => message.id !== id);
    clearTimeout(timers.get(id));
    timers.delete(id);
};

const announce = (tone, text) => {
    const id = ++nextId;

    messages.value = [...messages.value, { id, tone, text }];
    timers.set(
        id,
        setTimeout(() => dismiss(id), tone === 'success' ? 5000 : 9000),
    );
};

watch(
    () => page.props.flash,
    (flash) => {
        Object.keys(tones).forEach((tone) => {
            if (flash?.[tone]) {
                announce(tone, flash[tone]);
            }
        });
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
});
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-3 bottom-3 z-50 grid justify-items-stretch gap-2 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-[22rem]"
        role="status"
        aria-live="polite"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-out"
            leave-to-class="translate-y-1 opacity-0"
        >
            <div
                v-for="message in messages"
                :key="message.id"
                class="pointer-events-auto flex items-start gap-2 rounded-lg border border-line bg-surface p-3 shadow-overlay"
            >
                <AppIcon :name="tones[message.tone].icon" class="mt-px" :class="tones[message.tone].iconClass" />
                <p class="min-w-0 flex-1 text-ink">{{ message.text }}</p>
                <button
                    type="button"
                    class="btn btn-icon btn-ghost -my-1 -mr-1 min-h-7 min-w-7"
                    aria-label="Dismiss notification"
                    @click="dismiss(message.id)"
                >
                    <AppIcon name="close" :size="14" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

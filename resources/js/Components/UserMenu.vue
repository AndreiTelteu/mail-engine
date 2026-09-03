<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { routes } from '../routes';
import { setTheme, theme } from '../theme';

defineProps({
    user: { type: Object, required: true },
});

const open = ref(false);
const container = ref(null);

const close = () => {
    open.value = false;
};

const onDocumentPointerDown = (event) => {
    if (open.value && container.value && !container.value.contains(event.target)) {
        close();
    }
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape' && open.value) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<template>
    <div ref="container" class="relative">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md p-1.5 text-left transition-colors duration-100 hover:bg-hover"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="open = !open"
        >
            <span
                class="grid size-6 shrink-0 place-items-center rounded-full bg-accent-soft text-xs font-semibold text-accent-text"
                aria-hidden="true"
            >
                {{ user.initials }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium text-ink">{{ user.name }}</span>
            </span>
            <AppIcon name="chevronDown" :size="14" class="text-ink-subtle" />
        </button>

        <div
            v-if="open"
            role="menu"
            class="absolute bottom-[calc(100%+0.375rem)] left-0 z-30 w-60 origin-bottom rounded-xl border border-line bg-surface p-1 shadow-overlay"
        >
            <div class="border-b border-line px-2 pt-1.5 pb-2">
                <p class="truncate font-medium text-ink">{{ user.name }}</p>
                <p class="truncate text-sm text-ink-subtle">{{ user.email }}</p>
            </div>

            <div class="px-2 py-2">
                <p id="appearance-label" class="mb-1.5 text-sm text-ink-subtle">Appearance</p>
                <div class="grid grid-cols-2 gap-1" role="radiogroup" aria-labelledby="appearance-label">
                    <button
                        v-for="option in [
                            { value: 'dark', label: 'Dark', icon: 'moon' },
                            { value: 'light', label: 'Light', icon: 'sun' },
                        ]"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="theme === option.value"
                        class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-1.5 transition-colors duration-100"
                        :class="
                            theme === option.value
                                ? 'border-accent-line bg-accent-soft text-ink'
                                : 'border-line text-ink-muted hover:bg-hover hover:text-ink'
                        "
                        @click="setTheme(option.value)"
                    >
                        <AppIcon :name="option.icon" :size="14" />
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <Link
                :href="routes.logout"
                method="post"
                as="button"
                role="menuitem"
                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-ink-muted transition-colors duration-100 hover:bg-hover hover:text-ink"
                @click="close"
            >
                <AppIcon name="logOut" :size="14" />
                Log out
            </Link>
        </div>
    </div>
</template>

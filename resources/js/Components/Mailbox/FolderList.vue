<script setup>
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    folders: { type: Array, required: true },
    activeFolder: { type: String, default: '' },
    indexedTotal: { type: Number, default: 0 },
});

defineEmits(['select']);

const isActive = (name) => props.activeFolder === name;
</script>

<template>
    <section class="mt-4 grid gap-1" aria-labelledby="folder-list-heading">
        <h2 id="folder-list-heading" class="px-2 text-sm font-medium text-ink-subtle">Folders</h2>

        <ul class="grid gap-0.5">
            <li>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors duration-100"
                    :class="
                        isActive('')
                            ? 'bg-active font-medium text-ink'
                            : 'text-ink-muted hover:bg-hover hover:text-ink'
                    "
                    :aria-pressed="isActive('')"
                    @click="$emit('select', '')"
                >
                    <AppIcon name="inbox" :size="15" class="text-ink-subtle" />
                    <span class="min-w-0 flex-1 truncate">All mail</span>
                    <span class="tabular text-sm text-ink-subtle">{{ indexedTotal.toLocaleString() }}</span>
                </button>
            </li>
            <li v-for="folder in folders" :key="folder.name">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors duration-100"
                    :class="
                        isActive(folder.name)
                            ? 'bg-active font-medium text-ink'
                            : 'text-ink-muted hover:bg-hover hover:text-ink'
                    "
                    :aria-pressed="isActive(folder.name)"
                    @click="$emit('select', folder.name)"
                >
                    <AppIcon name="folder" :size="15" class="text-ink-subtle" />
                    <span class="min-w-0 flex-1 truncate" :title="folder.name">{{ folder.name }}</span>
                    <span class="tabular text-sm text-ink-subtle">{{ folder.count.toLocaleString() }}</span>
                </button>
            </li>
        </ul>

        <p v-if="! folders.length" class="px-2 pt-1 text-sm text-ink-subtle">
            Folders appear here once messages are indexed.
        </p>
    </section>
</template>

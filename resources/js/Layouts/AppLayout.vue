<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppMark from '../Components/AppMark.vue';
import AppNavigation from '../Components/AppNavigation.vue';
import FlashMessages from '../Components/FlashMessages.vue';
import SyncStatus from '../Components/SyncStatus.vue';
import UserMenu from '../Components/UserMenu.vue';
import { routes } from '../routes';

/**
 * The authenticated application shell: a persistent sidebar on wide screens and a
 * compact header with a navigation drawer on narrow ones.
 */
const props = defineProps({
    /** Shown in the mobile header so the small screen always names the surface. */
    title: { type: String, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const drawerOpen = ref(false);

const closeDrawer = () => {
    drawerOpen.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        closeDrawer();
    }
};

let stopNavigationListener;

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    stopNavigationListener = router.on('navigate', closeDrawer);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    stopNavigationListener?.();
});
</script>

<template>
    <div class="flex min-h-svh flex-col bg-canvas text-ink lg:h-svh lg:flex-row lg:overflow-hidden">
        <a
            href="#main-content"
            class="sr-only focus-visible:not-sr-only focus-visible:fixed focus-visible:top-3 focus-visible:left-3 focus-visible:z-50 focus-visible:rounded-md focus-visible:bg-surface focus-visible:px-3 focus-visible:py-2"
        >
            Skip to content
        </a>

        <!-- Mobile header -->
        <header
            class="sticky top-0 z-30 flex h-12 shrink-0 items-center gap-1 border-b border-line bg-surface px-2 lg:hidden"
        >
            <button
                type="button"
                class="btn btn-ghost btn-icon"
                :aria-expanded="drawerOpen"
                aria-controls="app-drawer"
                @click="drawerOpen = true"
            >
                <AppIcon name="menu" :size="18" />
                <span class="sr-only">Open navigation</span>
            </button>
            <p class="min-w-0 flex-1 truncate px-1 font-semibold tracking-[-0.01em]">{{ props.title }}</p>
            <slot name="header-actions" />
        </header>

        <!-- Mobile navigation drawer -->
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-out"
            leave-to-class="opacity-0"
        >
            <div v-if="drawerOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-black/60" @click="closeDrawer" />
                <div
                    id="app-drawer"
                    class="absolute inset-y-0 left-0 flex w-[17rem] max-w-[85vw] flex-col border-r border-line bg-surface"
                >
                    <div class="flex h-12 items-center justify-between border-b border-line px-3">
                        <Link :href="routes.dashboard" class="flex items-center gap-2 font-semibold tracking-[-0.01em]">
                            <AppMark :size="20" />
                            {{ page.props.appName }}
                        </Link>
                        <button type="button" class="btn btn-ghost btn-icon" @click="closeDrawer">
                            <AppIcon name="close" :size="16" />
                            <span class="sr-only">Close navigation</span>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        <AppNavigation />
                        <slot name="sidebar" />
                    </div>
                    <div class="grid gap-2 border-t border-line p-3">
                        <SyncStatus />
                        <UserMenu v-if="user" :user="user" />
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Desktop sidebar -->
        <aside
            class="hidden w-[15rem] shrink-0 flex-col border-r border-line bg-surface lg:flex"
            aria-label="Application"
        >
            <div class="flex h-12 items-center px-3">
                <Link
                    :href="routes.dashboard"
                    class="flex items-center gap-2 rounded-md font-semibold tracking-[-0.01em]"
                >
                    <AppMark :size="20" />
                    {{ page.props.appName }}
                </Link>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                <AppNavigation />
                <slot name="sidebar" />
            </div>

            <div class="grid gap-2 border-t border-line p-3">
                <SyncStatus />
                <UserMenu v-if="user" :user="user" />
            </div>
        </aside>

        <main
            id="main-content"
            class="flex min-h-0 min-w-0 flex-1 flex-col lg:overflow-hidden"
        >
            <slot />
        </main>

        <FlashMessages />
    </div>
</template>

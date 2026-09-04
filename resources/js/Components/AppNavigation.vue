<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import { routes } from '../routes';

const page = usePage();

const items = [
    { label: 'Overview', href: routes.dashboard, icon: 'inbox', match: /^\/dashboard/ },
    { label: 'Mail', href: routes.mailbox, icon: 'mail', match: /^\/emails/ },
    { label: 'Mail settings', href: routes.mailSettings, icon: 'settings', match: /^\/settings/ },
];

const current = computed(() => items.find((item) => item.match.test(page.url))?.href);
</script>

<template>
    <nav aria-label="Primary">
        <ul class="grid gap-0.5">
            <li v-for="item in items" :key="item.href">
                <Link
                    :href="item.href"
                    :prefetch="current === item.href ? false : 'mount'"
                    cache-for="1m"
                    :aria-current="current === item.href ? 'page' : undefined"
                    class="group flex items-center gap-2 rounded-md px-2 py-1.5 font-medium transition-colors duration-100"
                    :class="
                        current === item.href
                            ? 'bg-active text-ink'
                            : 'text-ink-muted hover:bg-hover hover:text-ink'
                    "
                >
                    <AppIcon
                        :name="item.icon"
                        class="transition-colors duration-100"
                        :class="current === item.href ? 'text-accent-text' : 'text-ink-subtle group-hover:text-ink-muted'"
                    />
                    {{ item.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>

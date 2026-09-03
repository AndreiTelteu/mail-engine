<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppMark from '../Components/AppMark.vue';
import { routes } from '../routes';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <Head :title="null" />

    <main class="grid min-h-svh place-items-center bg-canvas px-4 py-10 text-ink">
        <div class="w-full max-w-[22rem]">
            <div class="mb-6 inline-flex items-center gap-2 text-md font-semibold tracking-[-0.01em]">
                <AppMark :size="22" />
                {{ page.props.appName }}
            </div>

            <div class="panel p-6 shadow-overlay">
                <h1 class="text-xl font-semibold tracking-[-0.01em]">Your mailbox, searchable.</h1>
                <p class="mt-1.5 text-ink-muted">
                    A private, self-hosted client for the mailbox you already have. Messages are indexed on this
                    server so you can find and read any of them in seconds.
                </p>

                <div class="mt-5 grid gap-2">
                    <template v-if="user">
                        <Link :href="routes.mailbox" class="btn btn-primary btn-lg">
                            <AppIcon name="mail" :size="15" />
                            Open mail
                        </Link>
                        <Link :href="routes.dashboard" class="btn btn-secondary btn-lg">Go to overview</Link>
                    </template>
                    <template v-else>
                        <a :href="routes.login" class="btn btn-primary btn-lg">Log in</a>
                        <a :href="routes.register" class="btn btn-secondary btn-lg">Create an account</a>
                    </template>
                </div>
            </div>

            <ul class="mt-5 grid gap-1.5">
                <li
                    v-for="item in [
                        'Connects to your own IMAP mailbox',
                        'Full-text search over an index kept on this server',
                        'Message HTML sanitized and sandboxed before it renders',
                    ]"
                    :key="item"
                    class="flex items-start gap-2 text-sm text-ink-subtle"
                >
                    <AppIcon name="check" :size="13" class="mt-0.5 text-positive-text" />
                    {{ item }}
                </li>
            </ul>
        </div>
    </main>
</template>

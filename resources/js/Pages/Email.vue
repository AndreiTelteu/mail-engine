<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ email: Object });
</script>

<template>
    <Head :title="email.subject" />

    <main class="min-h-screen bg-zinc-950 p-4 text-zinc-100 sm:p-6">
        <article class="mx-auto max-w-6xl overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-zinc-800 p-5">
                <div>
                    <p class="text-xs text-zinc-500">{{ email.folder }} · {{ email.date }}</p>
                    <h1 class="mt-2 text-xl font-semibold">{{ email.subject }}</h1>
                    <p class="mt-2 text-sm text-zinc-300">{{ email.from }}</p>
                </div>
                <div class="flex gap-2">
                    <a :href="`/emails/${email.id}`" target="_blank" class="rounded-lg border border-zinc-700 px-3 py-2 text-sm hover:bg-zinc-800">Open in new tab</a>
                    <Link href="/emails" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium text-zinc-950 hover:bg-white">Back to mail</Link>
                </div>
            </header>

            <div class="space-y-6 p-5">
                <dl class="grid gap-3 rounded-xl border border-zinc-800 bg-zinc-950/50 p-4 text-sm">
                    <div><dt class="font-medium">From</dt><dd class="mt-1 text-zinc-400">{{ email.from }}</dd></div>
                    <div><dt class="font-medium">To</dt><dd class="mt-1 text-zinc-400">{{ email.to || 'No recipients recorded' }}</dd></div>
                    <div v-if="email.cc"><dt class="font-medium">Cc</dt><dd class="mt-1 text-zinc-400">{{ email.cc }}</dd></div>
                </dl>

                <iframe
                    class="min-h-[30rem] w-full rounded-xl bg-white"
                    sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin"
                    referrerpolicy="no-referrer"
                    :srcdoc="email.document"
                    :title="email.subject"
                />

                <section v-if="email.attachments.length" class="rounded-xl border border-zinc-800 p-4">
                    <h2 class="font-medium">Attachments</h2>
                    <p class="mt-1 text-sm text-zinc-400">Metadata only — file contents are not stored.</p>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        <li v-for="attachment in email.attachments" :key="`${attachment.filename}-${attachment.filetype}`" class="rounded-lg bg-zinc-800 px-3 py-2 text-sm">
                            <p>{{ attachment.filename }}</p><p class="mt-1 text-zinc-400">{{ attachment.filetype }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </article>
    </main>
</template>

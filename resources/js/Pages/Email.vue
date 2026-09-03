<script setup>
import { Head, Link } from '@inertiajs/vue3';
import QuotedUtilityLayout from '../Layouts/QuotedUtilityLayout.vue';

defineProps({ email: Object });
</script>

<template>
    <Head :title="email.subject" />
    <QuotedUtilityLayout title="Message record" description="Inspect the message in an isolated reading surface.">
        <article>
            <header class="border-b border-utility-ink p-5 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="max-w-3xl"><p class="font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em] text-utility-muted">{{ email.folder }} / {{ email.date }}</p><h2 class="mt-4 text-3xl font-black uppercase leading-[0.9] tracking-[-0.05em] sm:text-5xl">{{ email.subject }}</h2></div>
                    <div class="flex flex-wrap gap-2"><a :href="`/emails/${email.id}`" target="_blank" class="utility-button utility-button-secondary">New tab</a><Link href="/emails" class="utility-button">Back to mailbox</Link></div>
                </div>
            </header>
            <div class="grid lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="border-b border-utility-ink p-5 lg:border-b-0 lg:border-r">
                    <dl class="grid gap-5 text-sm"><div><dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-utility-muted">From</dt><dd class="mt-2 break-words font-bold">{{ email.from }}</dd></div><div><dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-utility-muted">To</dt><dd class="mt-2 break-words">{{ email.to || 'No recipients recorded' }}</dd></div><div v-if="email.cc"><dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-utility-muted">Cc</dt><dd class="mt-2 break-words">{{ email.cc }}</dd></div></dl>
                </aside>
                <div class="bg-white p-3 sm:p-6">
                    <iframe class="min-h-[38rem] w-full border border-utility-ink bg-white" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" referrerpolicy="no-referrer" :srcdoc="email.document" :title="email.subject" />
                </div>
            </div>
            <section v-if="email.attachments.length" class="border-t border-utility-ink p-5 sm:p-8"><h2 class="text-xl font-black uppercase tracking-[-0.04em]">Attachment record</h2><p class="mt-2 text-sm text-utility-muted">Metadata only. File contents are not stored.</p><ul class="mt-5 grid gap-3 sm:grid-cols-2"><li v-for="attachment in email.attachments" :key="`${attachment.filename}-${attachment.filetype}`" class="border border-utility-ink p-4"><p class="font-bold">{{ attachment.filename }}</p><p class="mt-1 font-mono text-xs uppercase text-utility-muted">{{ attachment.filetype }}</p></li></ul></section>
        </article>
    </QuotedUtilityLayout>
</template>

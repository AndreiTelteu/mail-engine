<script setup>
import { Head, Link } from '@inertiajs/vue3';
import QuotedUtilityLayout from '../Layouts/QuotedUtilityLayout.vue';
</script>

<template>
    <Head title="Workspace" />
    <QuotedUtilityLayout title="Ready to retrieve" description="Choose a station. Your mailbox remains private; search stays fast.">
        <div class="grid border-b border-utility-ink md:grid-cols-2">
            <Link href="/emails" class="group relative min-h-72 border-b border-utility-ink p-6 transition-colors hover:bg-utility-signal md:border-b-0 md:border-r sm:p-8"><span class="font-mono text-xs font-bold">01 / MAILBOX</span><h2 class="mt-16 max-w-sm text-4xl font-black uppercase leading-[0.86] tracking-[-0.06em]">Find the<br>message.</h2><span class="absolute bottom-7 right-7 text-3xl transition-transform group-hover:translate-x-2" aria-hidden="true">→</span></Link>
            <Link href="/settings/mail" class="group relative min-h-72 bg-utility-ink p-6 text-utility-paper transition-colors hover:bg-utility-signal hover:text-utility-ink sm:p-8"><span class="font-mono text-xs font-bold text-utility-signal group-hover:text-utility-ink">02 / CONNECTION</span><h2 class="mt-16 max-w-sm text-4xl font-black uppercase leading-[0.86] tracking-[-0.06em]">Set the<br>source.</h2><span class="absolute bottom-7 right-7 text-3xl transition-transform group-hover:translate-x-2" aria-hidden="true">→</span></Link>
        </div>
    </QuotedUtilityLayout>
</template>

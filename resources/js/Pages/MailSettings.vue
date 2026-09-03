<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ setting: Object, syncSession: Object });
const page = usePage();
const form = useForm({
    hostname: props.setting?.hostname ?? '',
    port: props.setting?.port ?? 993,
    username: props.setting?.username ?? '',
    password: '',
    encryption: props.setting?.encryption ?? 'ssl',
    isActive: props.setting?.isActive ?? true,
});

function testConnection() {
    form.post('/settings/mail/test', { preserveScroll: true });
}

function save() {
    form.put('/settings/mail', { preserveScroll: true });
}
</script>

<template>
    <Head title="Mail settings" />
    <main class="min-h-screen bg-zinc-950 p-4 text-zinc-100 sm:p-6">
        <section class="mx-auto max-w-2xl rounded-2xl border border-zinc-800 bg-zinc-900/70 p-6">
            <div class="flex items-start justify-between gap-4">
                <div><h1 class="text-xl font-semibold">Mail sync</h1><p class="mt-2 text-sm text-zinc-400">Connect your inbox so new messages can be indexed and searched.</p></div>
                <Link href="/emails" class="text-sm text-zinc-400 hover:text-zinc-100">Back to mail</Link>
            </div>
            <p v-if="syncSession" class="mt-5 rounded-lg border border-zinc-700 px-4 py-3 text-sm text-zinc-300">Sync status: {{ syncSession.status }}</p>
            <form class="mt-6 space-y-5" @submit.prevent="save">
                <label class="grid gap-2 text-sm">Hostname<input v-model="form.hostname" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5" autocomplete="off" placeholder="imap.example.com"><span v-if="form.errors.hostname" class="text-red-400">{{ form.errors.hostname }}</span></label>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm">Port<input v-model.number="form.port" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5" type="number" min="1" max="65535"><span v-if="form.errors.port" class="text-red-400">{{ form.errors.port }}</span></label>
                    <label class="grid gap-2 text-sm">Username<input v-model="form.username" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5" autocomplete="username"><span v-if="form.errors.username" class="text-red-400">{{ form.errors.username }}</span></label>
                </div>
                <label class="grid gap-2 text-sm">Password<input v-model="form.password" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5" type="password" autocomplete="current-password"><span v-if="form.errors.password" class="text-red-400">{{ form.errors.password }}</span></label>
                <fieldset><legend class="text-sm">Encryption</legend><div class="mt-2 flex gap-4 text-sm"><label><input v-model="form.encryption" type="radio" value="ssl"> SSL</label><label><input v-model="form.encryption" type="radio" value="tls"> TLS</label><label><input v-model="form.encryption" type="radio" value=""> None</label></div></fieldset>
                <label class="flex gap-3 rounded-xl border border-zinc-700 p-4 text-sm"><input v-model="form.isActive" type="checkbox"><span><b class="block">Enable background sync</b><span class="text-zinc-400">Allow scheduled jobs to fetch and index mail for this account.</span></span></label>
                <p v-if="page.props.flash.success" class="rounded-lg border border-emerald-800 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">{{ page.props.flash.success }}</p>
                <div class="flex flex-wrap gap-3"><button class="rounded-lg border border-zinc-700 px-4 py-2 text-sm disabled:opacity-50" type="button" :disabled="form.processing" @click="testConnection">{{ form.processing ? 'Testing...' : 'Test connection' }}</button><button class="rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-950 disabled:opacity-50" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save settings' }}</button><Link v-if="!syncSession?.isActive" as="button" href="/settings/mail/sync" method="post" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm">Start sync</Link></div>
            </form>
        </section>
    </main>
</template>

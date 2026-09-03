<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import QuotedUtilityLayout from '../Layouts/QuotedUtilityLayout.vue';

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

const testConnection = () => form.post('/settings/mail/test', { preserveScroll: true });
const save = () => form.put('/settings/mail', { preserveScroll: true });
</script>

<template>
    <Head title="Mail settings" />
    <QuotedUtilityLayout title="Connection station" description="Set the source for your private index. Credentials are encrypted before they are stored.">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_15rem]">
            <form class="p-5 sm:p-8" @submit.prevent="save">
                <div class="grid gap-6">
                    <label class="grid gap-2 text-sm font-bold uppercase tracking-wide">Hostname<input v-model="form.hostname" class="utility-input normal-case tracking-normal" autocomplete="off" placeholder="imap.example.com"><span v-if="form.errors.hostname" class="font-medium normal-case tracking-normal text-utility-signal">{{ form.errors.hostname }}</span></label>
                    <div class="grid gap-6 sm:grid-cols-2"><label class="grid gap-2 text-sm font-bold uppercase tracking-wide">Port<input v-model.number="form.port" class="utility-input normal-case tracking-normal" type="number" min="1" max="65535"><span v-if="form.errors.port" class="font-medium normal-case tracking-normal text-utility-signal">{{ form.errors.port }}</span></label><label class="grid gap-2 text-sm font-bold uppercase tracking-wide">Username<input v-model="form.username" class="utility-input normal-case tracking-normal" autocomplete="username"><span v-if="form.errors.username" class="font-medium normal-case tracking-normal text-utility-signal">{{ form.errors.username }}</span></label></div>
                    <label class="grid gap-2 text-sm font-bold uppercase tracking-wide">Password<input v-model="form.password" class="utility-input normal-case tracking-normal" type="password" autocomplete="current-password"><span v-if="form.errors.password" class="font-medium normal-case tracking-normal text-utility-signal">{{ form.errors.password }}</span></label>
                    <fieldset><legend class="text-sm font-bold uppercase tracking-wide">Encryption</legend><div class="mt-3 flex flex-wrap gap-3"><label v-for="option in [{ value: 'ssl', label: 'SSL' }, { value: 'tls', label: 'TLS' }, { value: '', label: 'None' }]" :key="option.label" class="cursor-pointer border border-utility-ink px-4 py-3 text-sm font-bold has-[:checked]:bg-utility-signal"><input v-model="form.encryption" class="sr-only" type="radio" :value="option.value"><span>{{ option.label }}</span></label></div></fieldset>
                    <label class="flex cursor-pointer gap-3 border border-utility-ink p-4 has-[:checked]:bg-utility-ink has-[:checked]:text-utility-paper"><input v-model="form.isActive" class="mt-1 size-4 accent-utility-signal" type="checkbox"><span><b class="block text-sm uppercase tracking-wide">Enable background sync</b><span class="mt-1 block text-sm opacity-70">Allow scheduled work to fetch and index mail.</span></span></label>
                    <p v-if="page.props.flash.success" class="border border-utility-ink bg-utility-signal p-4 text-sm font-bold">{{ page.props.flash.success }}</p>
                    <div class="flex flex-wrap gap-3"><button class="utility-button utility-button-secondary" type="button" :disabled="form.processing" @click="testConnection">{{ form.processing ? 'Testing…' : 'Test connection' }}</button><button class="utility-button" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save settings' }}</button></div>
                </div>
            </form>
            <aside class="border-t border-utility-ink bg-utility-ink p-5 text-utility-paper lg:border-l lg:border-t-0">
                <p class="font-mono text-[0.7rem] font-bold uppercase tracking-[0.12em] text-utility-signal">Sync status</p>
                <p v-if="syncSession" class="mt-4 text-2xl font-black uppercase">{{ syncSession.status }}</p><p v-else class="mt-4 text-lg font-bold">No active sync.</p>
                <Link v-if="!syncSession?.isActive" as="button" href="/settings/mail/sync" method="post" class="mt-6 border-b border-utility-paper pb-1 text-xs font-extrabold uppercase tracking-[0.08em] hover:text-utility-signal">Start sync now</Link>
                <p class="mt-10 border-t border-utility-paper/30 pt-4 text-sm leading-5 text-utility-paper/65">Your mailbox data stays scoped to your account. Search reads from your private index.</p>
            </aside>
        </div>
    </QuotedUtilityLayout>
</template>

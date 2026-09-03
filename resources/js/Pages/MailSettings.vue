<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import ImapSettingsForm from '../Components/Settings/ImapSettingsForm.vue';
import PageHeader from '../Components/PageHeader.vue';
import SyncPanel from '../Components/Settings/SyncPanel.vue';

defineProps({
    setting: { type: Object, default: null },
    syncSession: { type: Object, default: null },
});
</script>

<template>
    <Head title="Mail settings" />

    <AppLayout title="Mail settings">
        <div class="min-h-0 flex-1 lg:overflow-y-auto">
            <PageHeader
                title="Mail settings"
                description="Connect the IMAP mailbox this server indexes. Credentials are encrypted before they are stored and are never sent to the browser."
            />

            <div class="grid items-start gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:p-6">
                <section class="panel p-4 lg:p-5" aria-labelledby="connection-heading">
                    <h2 id="connection-heading" class="sr-only">Mailbox connection</h2>
                    <ImapSettingsForm :setting="setting" />
                </section>

                <SyncPanel :session="syncSession" :configured="Boolean(setting)" />
            </div>
        </div>
    </AppLayout>
</template>

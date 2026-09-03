<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import EmailReader from '../Components/Mailbox/EmailReader.vue';
import { routes } from '../routes';

defineProps({
    email: { type: Object, required: true },
});
</script>

<template>
    <Head :title="email.subject" />

    <AppLayout title="Message">
        <div class="flex min-h-0 flex-1 flex-col lg:overflow-y-auto">
            <div class="flex shrink-0 items-center gap-1 border-b border-line bg-surface px-2 py-1.5">
                <Link :href="`${routes.mailbox}?email=${email.id}`" class="btn btn-ghost">
                    <AppIcon name="chevronLeft" :size="15" />
                    Back to mail
                </Link>
            </div>

            <EmailReader :email="email" variant="page" />
        </div>
    </AppLayout>
</template>

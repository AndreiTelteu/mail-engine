<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '../AppIcon.vue';
import Banner from '../Banner.vue';
import FormField from './FormField.vue';
import Spinner from '../Spinner.vue';
import { routes } from '../../routes';

const props = defineProps({
    setting: { type: Object, default: null },
});

const encryptions = [
    { value: 'ssl', label: 'SSL', port: 993 },
    { value: 'tls', label: 'TLS', port: 143 },
    { value: '', label: 'None', port: 143 },
];

const form = useForm({
    hostname: props.setting?.hostname ?? '',
    port: props.setting?.port ?? 993,
    username: props.setting?.username ?? '',
    password: '',
    encryption: props.setting?.encryption ?? 'ssl',
    isActive: props.setting?.isActive ?? true,
});

/** Which button is waiting, so only that one shows progress. */
const pending = ref(null);

const submit = (action) => {
    pending.value = action;

    if (action === 'test') {
        form.post(routes.mailSettingsTest, {
            preserveScroll: true,
            onFinish: () => (pending.value = null),
        });

        return;
    }

    form.put(routes.mailSettings, {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
        onFinish: () => (pending.value = null),
    });
};

const selectEncryption = (option) => {
    const previous = encryptions.find((candidate) => candidate.value === form.encryption);

    // Keep the port in step with the usual default, but never overwrite a custom one.
    if (previous && form.port === previous.port) {
        form.port = option.port;
    }

    form.encryption = option.value;
};
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit('save')">
        <Banner v-if="form.errors.connection" tone="error" title="Could not connect to the mailbox">
            {{ form.errors.connection }} Check the hostname, port, and credentials, then test again.
        </Banner>

        <fieldset class="grid gap-4">
            <legend class="mb-1 font-semibold text-ink">Server</legend>

            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_7rem]">
                <FormField
                    id="hostname"
                    label="Hostname"
                    hint="The IMAP host, for example imap.fastmail.com."
                    :error="form.errors.hostname"
                >
                    <template #default="{ describedBy }">
                        <input
                            id="hostname"
                            v-model="form.hostname"
                            class="field-input"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="imap.example.com"
                            :aria-invalid="form.errors.hostname ? 'true' : undefined"
                            :aria-describedby="describedBy"
                            :disabled="form.processing"
                        />
                    </template>
                </FormField>

                <FormField id="port" label="Port" :error="form.errors.port">
                    <template #default="{ describedBy }">
                        <input
                            id="port"
                            v-model.number="form.port"
                            class="field-input tabular"
                            type="number"
                            min="1"
                            max="65535"
                            :aria-invalid="form.errors.port ? 'true' : undefined"
                            :aria-describedby="describedBy"
                            :disabled="form.processing"
                        />
                    </template>
                </FormField>
            </div>

            <div class="grid gap-1.5">
                <span id="encryption-label" class="field-label">Encryption</span>
                <div
                    class="inline-flex w-fit rounded-md border border-line-strong bg-raised p-0.5"
                    role="radiogroup"
                    aria-labelledby="encryption-label"
                >
                    <button
                        v-for="option in encryptions"
                        :key="option.label"
                        type="button"
                        role="radio"
                        :aria-checked="form.encryption === option.value"
                        class="min-h-7 rounded-sm px-3 font-medium transition-colors duration-100"
                        :class="
                            form.encryption === option.value
                                ? 'bg-accent-solid text-white'
                                : 'text-ink-muted hover:text-ink'
                        "
                        :disabled="form.processing"
                        @click="selectEncryption(option)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <p class="field-hint">
                    Most mailboxes use SSL on port 993. Choose TLS for STARTTLS on port 143, or None only on a trusted network.
                </p>
                <p v-if="form.errors.encryption" class="field-error">{{ form.errors.encryption }}</p>
            </div>
        </fieldset>

        <fieldset class="grid gap-4 border-t border-line pt-5">
            <legend class="mb-1 font-semibold text-ink">Account</legend>

            <FormField id="username" label="Username" :error="form.errors.username">
                <template #default="{ describedBy }">
                    <input
                        id="username"
                        v-model="form.username"
                        class="field-input"
                        type="text"
                        autocomplete="username"
                        spellcheck="false"
                        placeholder="you@example.com"
                        :aria-invalid="form.errors.username ? 'true' : undefined"
                        :aria-describedby="describedBy"
                        :disabled="form.processing"
                    />
                </template>
            </FormField>

            <FormField
                id="password"
                label="Password"
                :hint="
                    setting
                        ? 'Leave blank to keep the saved password. Stored encrypted on this server.'
                        : 'Stored encrypted on this server and used only to read your mailbox.'
                "
                :error="form.errors.password"
            >
                <template #default="{ describedBy }">
                    <input
                        id="password"
                        v-model="form.password"
                        class="field-input"
                        type="password"
                        autocomplete="current-password"
                        :placeholder="setting ? '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022' : ''"
                        :aria-invalid="form.errors.password ? 'true' : undefined"
                        :aria-describedby="describedBy"
                        :disabled="form.processing"
                    />
                </template>
            </FormField>
        </fieldset>

        <div class="flex items-start gap-3 rounded-lg border border-line bg-raised p-3">
            <button
                type="button"
                role="switch"
                :aria-checked="form.isActive"
                aria-labelledby="sync-switch-label"
                class="mt-0.5 inline-flex h-4.5 w-8 shrink-0 items-center rounded-full border transition-colors duration-150"
                :class="form.isActive ? 'border-accent-solid bg-accent-solid' : 'border-line-strong bg-active'"
                :disabled="form.processing"
                @click="form.isActive = ! form.isActive"
            >
                <span
                    class="size-3 rounded-full bg-white transition-transform duration-150 ease-out"
                    :class="form.isActive ? 'translate-x-4' : 'translate-x-0.5'"
                />
            </button>
            <div class="min-w-0">
                <span id="sync-switch-label" class="block font-medium text-ink">Background synchronization</span>
                <p class="text-ink-muted">
                    When on, a scheduled job checks your folders every 15 minutes and indexes new messages.
                    Turn it off to pause indexing without deleting the connection.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary btn-lg" :disabled="form.processing">
                <Spinner v-if="pending === 'save'" />
                {{ pending === 'save' ? 'Testing and saving\u2026' : 'Save connection' }}
            </button>
            <button
                type="button"
                class="btn btn-secondary btn-lg"
                :disabled="form.processing"
                @click="submit('test')"
            >
                <Spinner v-if="pending === 'test'" />
                <AppIcon v-else name="plug" :size="15" />
                {{ pending === 'test' ? 'Connecting\u2026' : 'Test connection' }}
            </button>
            <p class="text-sm text-ink-subtle">Saving tests the connection first.</p>
        </div>
    </form>
</template>

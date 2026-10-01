<script setup>
import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import { routes } from '../../routes';

const props = defineProps({
    tokens: { type: Array, default: () => [] },
    createdTokenId: { type: Number, default: null },
});

const dialog = ref(null);
const selectedToken = ref(null);
const isCreating = ref(false);
const deletingTokenId = ref(null);
const copyStatus = ref('');

function showLink(token) {
    selectedToken.value = token;
    copyStatus.value = '';
    dialog.value?.showModal();
}

function closeLink() {
    dialog.value?.close();
}

function createToken() {
    router.post(routes.mcpTokens, {}, {
        onStart: () => { isCreating.value = true; },
        onFinish: () => { isCreating.value = false; },
    });
}

function deleteToken(token) {
    if (!window.confirm('Delete this MCP token? Its connection link will stop working immediately.')) {
        return;
    }

    router.delete(routes.mcpToken(token.id), {
        onStart: () => { deletingTokenId.value = token.id; },
        onFinish: () => { deletingTokenId.value = null; },
    });
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(selectedToken.value.url);
        copyStatus.value = 'Link copied';
    } catch {
        copyStatus.value = 'Copy failed. Select the link and copy it manually.';
    }
}

watch(
    () => props.createdTokenId,
    async (id) => {
        if (!id) {
            return;
        }

        await nextTick();
        const token = props.tokens.find((item) => item.id === id);

        if (token) {
            showLink(token);
        }
    },
    { immediate: true, flush: 'post' },
);
</script>

<template>
    <section class="panel" aria-labelledby="mcp-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div class="flex items-start gap-3">
                <span class="rounded-md bg-active p-2 text-accent-text"><AppIcon name="plug" :size="18" /></span>
                <div>
                    <h2 id="mcp-heading" class="font-semibold text-ink">MCP access</h2>
                    <p class="text-sm text-ink-muted">Connect an MCP client to search and read your mail.</p>
                </div>
            </div>
            <button type="button" class="btn btn-primary" :disabled="isCreating" @click="createToken">
                <AppIcon name="key" :size="15" />
                {{ isCreating ? 'Creating…' : 'Create Token' }}
            </button>
        </div>

        <div class="p-4">
            <p v-if="!tokens.length" class="text-sm text-ink-muted">No MCP tokens yet. Create one to get a connection link.</p>
            <ul v-else class="divide-y divide-line overflow-hidden rounded-md border border-line">
                <li v-for="token in tokens" :key="token.id" class="flex flex-wrap items-center justify-between gap-3 bg-raised px-3 py-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 font-medium text-ink"><AppIcon name="key" :size="15" /> Token #{{ token.id }}</p>
                        <p class="text-sm text-ink-subtle">
                            Created {{ new Date(token.createdAt).toLocaleString() }}
                            <span v-if="token.lastUsedAt"> · Last used {{ new Date(token.lastUsedAt).toLocaleString() }}</span>
                            <span v-else> · Never used</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" :aria-label="`Show link for token #${token.id}`" @click="showLink(token)">
                            <AppIcon name="externalLink" :size="14" /> Show link
                        </button>
                        <button type="button" class="btn btn-secondary text-critical-text" :disabled="deletingTokenId === token.id" :aria-label="`Delete token #${token.id}`" @click="deleteToken(token)">
                            <AppIcon name="trash" :size="14" /> Delete
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <dialog ref="dialog" aria-labelledby="mcp-link-title" class="w-[min(36rem,calc(100%-2rem))] rounded-xl border border-line bg-raised p-0 text-ink shadow-2xl backdrop:bg-black/70" @close="selectedToken = null">
            <div v-if="selectedToken" class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="mcp-link-title" class="text-lg font-semibold">MCP connection link</h3>
                        <p class="mt-1 text-sm text-ink-muted">Token #{{ selectedToken.id }} · Paste this URL into your MCP client.</p>
                    </div>
                    <button type="button" class="rounded-md p-1.5 text-ink-muted hover:bg-active hover:text-ink" aria-label="Close" @click="closeLink"><AppIcon name="close" :size="18" /></button>
                </div>
                <label for="mcp-url" class="mt-5 block text-sm font-medium">Full MCP URL</label>
                <input id="mcp-url" :value="selectedToken.url" readonly class="field-input mt-2 w-full font-mono text-xs" @focus="$event.target.select()" />
                <p class="mt-2 text-sm text-ink-subtle">This link grants access to your mailbox. Keep it private and delete the token when you no longer need it.</p>
                <div class="mt-5 flex items-center justify-between gap-3">
                    <span class="text-sm text-ink-muted" role="status">{{ copyStatus }}</span>
                    <button type="button" class="btn btn-primary" @click="copyLink"><AppIcon name="copy" :size="15" /> Copy link</button>
                </div>
            </div>
        </dialog>
    </section>
</template>

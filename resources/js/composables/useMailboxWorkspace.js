import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { routes } from '../routes';

const TYPING_TAGS = ['INPUT', 'TEXTAREA', 'SELECT'];

const isTyping = (target) =>
    target instanceof HTMLElement && (TYPING_TAGS.includes(target.tagName) || target.isContentEditable);

/**
 * Owns the mailbox workspace's route state: the query, folder filter, page, and
 * selected message all live in the URL so a message stays linkable and the back
 * button behaves. Also binds the keyboard shortcuts the list is operated with.
 *
 * @param {object} props The Mailbox page props.
 */
export function useMailboxWorkspace(props) {
    const query = ref(props.filters.query ?? '');
    const searching = ref(false);
    const searchField = ref(null);

    let debounceTimer;
    let suppressNextSearch = false;

    const visit = (overrides = {}, only = ['filters', 'emails']) => {
        router.get(
            routes.mailbox,
            {
                query: query.value || undefined,
                folder: props.filters.folder || undefined,
                email: props.filters.email || undefined,
                page: props.emails.currentPage > 1 ? props.emails.currentPage : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
                onStart: () => (searching.value = true),
                onFinish: () => (searching.value = false),
            },
        );
    };

    watch(query, () => {
        if (suppressNextSearch) {
            suppressNextSearch = false;

            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => visit({ page: undefined }), 250);
    });

    const selectFolder = (folder) => visit({ folder: folder || undefined, page: undefined });
    const selectEmail = (id) => visit({ email: id }, ['filters', 'selectedEmail']);
    const emailHref = (id) => {
        const parameters = new URLSearchParams();

        if (query.value) {
            parameters.set('query', query.value);
        }

        if (props.filters.folder) {
            parameters.set('folder', props.filters.folder);
        }

        parameters.set('email', id);

        if (props.emails.currentPage > 1) {
            parameters.set('page', props.emails.currentPage);
        }

        return `${routes.mailbox}?${parameters.toString()}`;
    };
    const closeEmail = () => visit({ email: undefined }, ['filters', 'selectedEmail']);
    const goToPage = (page) => visit({ page: page > 1 ? page : undefined });

    const reset = () => {
        clearTimeout(debounceTimer);
        suppressNextSearch = true;
        query.value = '';
        visit({ query: undefined, folder: undefined, page: undefined });
    };

    const clearQuery = () => {
        clearTimeout(debounceTimer);
        suppressNextSearch = true;
        query.value = '';
        visit({ query: undefined, page: undefined });
    };

    const focusSearch = () => {
        searchField.value?.querySelector('input')?.focus();
    };

    const step = (offset) => {
        const messages = props.emails.data;

        if (! messages.length) {
            return;
        }

        const current = messages.findIndex((email) => email.id === props.filters.email);
        const next = current === -1 ? 0 : Math.min(Math.max(current + offset, 0), messages.length - 1);

        if (messages[next].id !== props.filters.email) {
            selectEmail(messages[next].id);
        }
    };

    const onKeydown = (event) => {
        if (event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        if (event.key === 'Escape') {
            if (isTyping(event.target)) {
                event.target.blur();
            } else if (props.filters.email) {
                closeEmail();
            }

            return;
        }

        if (isTyping(event.target)) {
            return;
        }

        if (event.key === '/') {
            event.preventDefault();
            focusSearch();

            return;
        }

        if (event.key === 'j' || event.key === 'ArrowDown') {
            event.preventDefault();
            step(1);
        }

        if (event.key === 'k' || event.key === 'ArrowUp') {
            event.preventDefault();
            step(-1);
        }
    };

    onMounted(() => document.addEventListener('keydown', onKeydown));

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', onKeydown);
        clearTimeout(debounceTimer);
    });

    return {
        query,
        searching,
        searchField,
        isFiltered: computed(() => Boolean(props.filters.query || props.filters.folder)),
        selectFolder,
        emailHref,
        closeEmail,
        goToPage,
        reset,
        clearQuery,
    };
}

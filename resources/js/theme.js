import { ref } from 'vue';

/**
 * Appearance is stored under the key Flux also reads, so the Inertia application
 * and the Flux-rendered authentication screens share one preference. Dark is the
 * product default; see resources/css/app.css.
 */
const STORAGE_KEY = 'flux.appearance';

const read = () => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === 'light' ? 'light' : 'dark';
    } catch {
        return 'dark';
    }
};

export const theme = ref(read());

const apply = (value) => {
    document.documentElement.dataset.theme = value;
    document.documentElement.classList.toggle('dark', value === 'dark');
};

export const setTheme = (value) => {
    theme.value = value === 'light' ? 'light' : 'dark';

    try {
        window.localStorage.setItem(STORAGE_KEY, theme.value);
    } catch {
        // Storage is unavailable; the choice still applies for this visit.
    }

    apply(theme.value);
};

export const toggleTheme = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');

export const initializeTheme = () => {
    theme.value = read();
    apply(theme.value);
};

export const accentColor = () =>
    getComputedStyle(document.documentElement).getPropertyValue('--app-accent').trim() || '#2563eb';

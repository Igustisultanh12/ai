import { ref } from 'vue';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'xzy-theme';

const readStored = (): Theme | null => {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return v === 'light' || v === 'dark' ? v : null;
    } catch {
        return null;
    }
};

const systemTheme = (): Theme =>
    typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

const theme = ref<Theme>(
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark') ? 'dark' : readStored() ?? systemTheme()
);

const apply = (t: Theme) => {
    const root = document.documentElement;
    // Briefly disable transitions so every surface switches at once
    root.classList.add('theme-switching');
    root.classList.toggle('dark', t === 'dark');
    root.style.colorScheme = t;
    window.setTimeout(() => root.classList.remove('theme-switching'), 50);
};

/**
 * Light / dark theme (same token system as the PT Kreatif Sky Abadi company site).
 * Follows the OS preference until the user picks one explicitly.
 */
export function useTheme() {
    const setTheme = (t: Theme) => {
        theme.value = t;
        try {
            localStorage.setItem(STORAGE_KEY, t);
        } catch {
            /* storage unavailable */
        }
        apply(t);
    };

    const toggleTheme = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');

    return { theme, setTheme, toggleTheme };
}

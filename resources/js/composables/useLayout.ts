import { ref, watch } from 'vue';

const KEY = 'xzy-sidebar-collapsed';

const readCollapsed = (): boolean => {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
};

const isSidebarCollapsed = ref<boolean>(typeof window !== 'undefined' ? readCollapsed() : false);

watch(isSidebarCollapsed, (v) => {
    try {
        localStorage.setItem(KEY, v ? '1' : '0');
    } catch {
        /* storage unavailable */
    }
});

/** Shared app-shell state so the content area follows the sidebar width. */
export function useLayout() {
    return { isSidebarCollapsed };
}

/**
 * Tiny event bus so the navbar quota meter refreshes right after work that
 * consumes tokens (chat replies, vibecoding runs) instead of only on page load.
 */
export const USAGE_CHANGED_EVENT = 'xzy:usage-changed';

export function notifyUsageChanged(): void {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(USAGE_CHANGED_EVENT));
    }
}

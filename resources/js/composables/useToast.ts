import { ref } from 'vue';

export interface ToastMessage {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    title?: string;
    message: string;
    duration?: number;
}

const toasts = ref<ToastMessage[]>([]);

export function useToast() {
    const add = (type: ToastMessage['type'], message: string, title?: string, duration: number = 4000) => {
        const id = Math.random().toString(36).substring(2, 9);
        toasts.value.push({ id, type, title, message, duration });

        if (duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }
    };

    const remove = (id: string) => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    };

    return {
        toasts,
        success: (msg: string, title?: string) => add('success', msg, title),
        error: (msg: string, title?: string) => add('error', msg, title, 6000),
        warning: (msg: string, title?: string) => add('warning', msg, title),
        info: (msg: string, title?: string) => add('info', msg, title),
        remove,
    };
}

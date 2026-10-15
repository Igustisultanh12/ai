import { ref } from 'vue';
import { api } from '@/api/client';

export interface ChatModel {
    id: string;
    available: boolean;
    multiplier: number | null;
    description?: string | null;
}

const models = ref<ChatModel[]>([]);
const defaultModel = ref('');
const source = ref('');
let loading: Promise<void> | null = null;

/**
 * Shared TokenomixAI model catalog (GET /api/models, live from GET /v1/models).
 */
export function useModels() {
    const load = (force = false): Promise<void> => {
        if (loading && !force) return loading;
        loading = api
            .get('/api/models')
            .then((res: any) => {
                models.value = res.models || [];
                defaultModel.value = res.default || models.value.find(m => m.available)?.id || '';
                source.value = res.source || '';
            })
            .catch((e) => {
                loading = null;
                throw e;
            });
        return loading;
    };

    const isAvailable = (id?: string | null) => !!id && models.value.some(m => m.id === id && m.available);

    const formatMultiplier = (m: number | null) =>
        m === null || m === undefined ? '' : Number.isInteger(m) ? String(m) : m.toFixed(2).replace(/0+$/, '');

    const label = (m: ChatModel) =>
        `${m.id}${m.multiplier ? ` · ×${formatMultiplier(m.multiplier)}` : ''}${m.available ? '' : ' (stok habis)'}`;

    return { models, defaultModel, source, load, isAvailable, label };
}

import { ref, computed } from 'vue';
import { api } from '@/api/client';

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    status: 'active' | 'suspended';
    daily_token_quota: number;
    monthly_token_quota: number;
    concurrency_limit: number;
    email_verified_at?: string;
    created_at?: string;
}

const user = ref<User | null>(null);
const isLoading = ref<boolean>(true);
const mfaVerified = ref<boolean>(false);
const hasTwoFactor = ref<boolean>(false);

export function useAuth() {
    const isAuthenticated = computed(() => !!user.value);
    const isAdmin = computed(() => user.value?.role === 'admin');

    const checkAuth = async () => {
        isLoading.value = true;
        try {
            const res = await api.get<{ user: User; mfa_verified: boolean; has_two_factor: boolean }>('/api/auth/me');
            user.value = res.user;
            mfaVerified.value = res.mfa_verified;
            hasTwoFactor.value = res.has_two_factor;
        } catch (e) {
            user.value = null;
            mfaVerified.value = false;
            hasTwoFactor.value = false;
        } finally {
            isLoading.value = false;
        }
    };

    const logout = async () => {
        try {
            await api.post('/api/auth/logout');
        } finally {
            user.value = null;
            mfaVerified.value = false;
            hasTwoFactor.value = false;
            window.location.href = '/login';
        }
    };

    return {
        user,
        isLoading,
        mfaVerified,
        hasTwoFactor,
        isAuthenticated,
        isAdmin,
        checkAuth,
        logout,
    };
}

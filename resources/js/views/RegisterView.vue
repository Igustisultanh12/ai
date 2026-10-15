<template>
  <AuthShell eyebrow="Daftar" title="Bikin akun gratis" subtitle="Satu menit saja, lalu langsung ngoding bareng AI.">
    <form class="space-y-5" @submit.prevent="handleRegister">
      <div v-if="errorMessage" class="flex items-start gap-2 rounded-2xl border-2 border-rose-400 bg-rose-500/10 p-3 text-sm text-rose-600 dark:text-rose-300">
        <Icon name="alert-triangle" class="mt-0.5 h-4 w-4" />
        <span>{{ errorMessage }}</span>
      </div>

      <div>
        <label class="label" for="reg-name">Nama lengkap</label>
        <input id="reg-name" v-model="form.name" type="text" required autocomplete="name" class="input" placeholder="Nama kamu" />
      </div>

      <div>
        <label class="label" for="reg-email">Email</label>
        <input id="reg-email" v-model="form.email" type="email" required autocomplete="email" class="input" placeholder="nama@domain.com" />
      </div>

      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="label" for="reg-password">Password</label>
          <input id="reg-password" v-model="form.password" type="password" required autocomplete="new-password" class="input" placeholder="Minimal 8 karakter" />
        </div>
        <div>
          <label class="label" for="reg-password2">Ulangi password</label>
          <input
            id="reg-password2"
            v-model="form.password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            class="input"
            :class="passwordMismatch ? 'border-rose-400 focus:border-rose-500' : ''"
            placeholder="Ketik ulang"
          />
        </div>
      </div>

      <div class="space-y-1.5">
        <div class="flex gap-1.5">
          <span v-for="n in 4" :key="n" class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="n <= passwordStrength ? strengthColor : 'bg-surface-2'"></span>
        </div>
        <p class="text-xs text-muted">
          {{ passwordMismatch ? 'Password belum sama.' : strengthLabel }}
        </p>
      </div>

      <button v-magnetic type="submit" :disabled="isLoading || passwordMismatch" class="btn btn-primary btn-lg shine w-full">
        <Icon v-if="isLoading" name="loader" class="h-5 w-5 animate-spin" />
        <Icon v-else name="sparkles" class="h-5 w-5" />
        {{ isLoading ? 'Mendaftarkan akun...' : 'Buat Akun' }}
      </button>
    </form>

    <template #footer>
      Sudah punya akun?
      <router-link to="/login" class="font-bold text-violet-600 underline decoration-2 underline-offset-4 hover:text-fuchsia-600 dark:text-violet-300">Masuk di sini</router-link>
    </template>
  </AuthShell>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useAuth } from '@/stores/auth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const { checkAuth } = useAuth();
const toast = useToast();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isLoading = ref(false);

const passwordMismatch = computed(() => form.password_confirmation.length > 0 && form.password !== form.password_confirmation);

// Simple strength hint (length + character variety)
const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) return 0;
    let score = p.length >= 8 ? 1 : 0;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p) || p.length >= 14) score++;
    return Math.max(1, score);
});
const strengthLabel = computed(() =>
    ['Gunakan minimal 8 karakter.', 'Password lemah.', 'Lumayan, tambahkan angka atau simbol.', 'Password kuat.', 'Password sangat kuat.'][form.password ? passwordStrength.value : 0]
);
const strengthColor = computed(() => ['bg-rose-500', 'bg-rose-500', 'bg-yellow-400', 'bg-lime-400', 'bg-emerald-500'][passwordStrength.value]);
const errorMessage = ref('');

const handleRegister = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await api.post('/api/auth/register', form);
        await checkAuth();
        toast.success('Pendaftaran berhasil. Selamat datang di XZY!');
        router.push('/dashboard');
    } catch (e: any) {
        errorMessage.value = e.message || 'Pendaftaran gagal. Periksa input data Anda.';
    } finally {
        isLoading.value = false;
    }
};
</script>
